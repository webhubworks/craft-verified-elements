<?php /** @noinspection PhpUnused */

namespace webhubworks\verifiedelements\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\enums\VerificationPeriod;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\services\VerificationFieldsRenderer;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Handles rendering and data endpoints for the Verified Elements indexes and the verification modals.
 */
class IndexController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();

        // Most actions in this controller support the plugin's own CP pages, so they require
        // plugin access.
        if (in_array($action->id, ['request-period', 'resolve-date'], true)) {
            // These two modal endpoints are the exception: they're opened by the "Verify" bulk
            // action, which are also available on Craft's regular Entries and Assets indexes.
            // Users who verify content from there don't necessarily have access to the plugin's
            // pages, so these endpoints only require a verify permission.
            $this->requireVerifyPermission();
        }
        else {
            $this->requirePermission(Permission::AccessPlugin->value);
        }

        return parent::beforeAction($action);
    }

    /**
     * Redirects the plugin's landing URL to the first available subpage, so the URL always
     * matches a subnav item and the sidebar keeps the subnav expanded and highlighted.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        if (Feature::EntryVerification->isEnabled()) {
            return $this->redirect(UrlHelper::cpUrl(Plugin::HANDLE . '/entries'));
        }

        return $this->redirect(UrlHelper::cpUrl(Plugin::HANDLE . '/assets'));
    }

    /**
     * Renders the Verified Elements index page for entries.
     *
     * @return Response
     */
    public function actionEntries(): Response
    {
        return $this->renderTemplate(
            Plugin::HANDLE . '/index.twig',
            [
                'elementType' => VerifiedEntry::class,
                'selectedSubnavItem' => 'entries',
            ]
        );
    }

    /**
     * Renders the Verified Elements index page for assets.
     *
     * @return Response
     */
    public function actionAssets(): Response
    {
        return $this->renderTemplate(
            Plugin::HANDLE . '/index.twig',
            [
                'elementType' => VerifiedAsset::class,
                'selectedSubnavItem' => 'assets',
            ]
        );
    }

    /**
     * Renders the verification period selection modal.
     *
     * @return Response
     */
    public function actionRequestPeriod(): Response
    {
        return $this->asCpModal()
            ->action(Plugin::HANDLE . '/index/resolve-date')
            ->contentTemplate(
                Plugin::HANDLE . '/_modals/_period.twig',
                ['periodSelectOptions' => VerificationFieldsRenderer::periodSelectOptions(true)]
            );
    }

    /**
     * Resolves a verification period to a concrete date and returns it as JSON.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionResolveDate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $verificationPeriod = $this->request->getRequiredBodyParam('verificationPeriod');

        if ($verificationPeriod === VerificationPeriod::SpecificDate->value) {
            $inputDate = $this->request->getRequiredBodyParam('specificDate');
            $date = DateHelper::toDateTime($inputDate);
        }
        elseif ($verificationPeriod === VerificationPeriod::Indefinitely->value) {
            $date = null;
        }
        else {
            $interval = DateHelper::createDateInterval($verificationPeriod);
            $date = DateTimeHelper::now()->add($interval);
        }

        if ($date === false) {
            return $this->asFailure(Craft::t(Plugin::HANDLE, 'Not a valid date.'));
        }

        return $this->asJson([
            'date' => $date?->format('Y-m-d'),
        ]);
    }

    /**
     * Returns paginated and sorted element data for the admin table.
     *
     * @param int|null $userId
     * @param string|null $elementType URI segment ('entries', 'assets'); null includes every
     * element type the current edition enables.
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionTableData(?int $userId = null, ?string $elementType = null): Response
    {
        $this->requireAcceptsJson();

        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);

        $elementTypes = ElementType::enabledTypes();
        if ($elementType !== null) {
            $requestedType = ElementType::fromUriSegment($elementType)->value;
            $elementTypes = in_array($requestedType, $elementTypes, true) ? [$requestedType] : [];
        }

        // The requested element type isn't enabled in this edition, so there's nothing to list.
        if (empty($elementTypes)) {
            return $this->asJson([
                'pagination' => AdminTable::paginationLinks($page, 0, $limit),
                'data' => [],
            ]);
        }

        $orderBy = match ($this->request->getParam('sort.0.field')) {
            '__slot:title' => 'title',
            'isVerified', 'verifiedUntilDate' => 'verifiedUntilDate',
            'dateUpdated' => 'dateUpdated',
            'siteName' => 'siteName',
            default => 'containerName',
        };

        $sortDir = match ($this->request->getParam('sort.0.direction')) {
            'desc' => SORT_DESC,
            default => SORT_ASC,
        };

        $inScopeSiteIds = Plugin::getInstance()->getPluginSettings()->getInScopeSiteIds();

        [$results, $total] = Plugin::getInstance()->getReviewers()->getPaginatedElements(
            $page,
            $limit,
            $inScopeSiteIds,
            $sortDir,
            $orderBy,
            $userId,
            elementTypes: $elementTypes,
        );

        $pagination = AdminTable::paginationLinks($page, $total, $limit);

        return $this->asJson([
            'pagination' => $pagination,
            'data' => $results,
        ]);
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Requires the current user to hold at least one per-type verify permission.
     *
     * @throws ForbiddenHttpException
     */
    private function requireVerifyPermission(): void
    {
        if (Permission::VerifyEntries->isGranted() || Permission::VerifyAssets->isGranted()) {
            return;
        }

        throw new ForbiddenHttpException('User is not authorized to perform this action.');
    }
}
