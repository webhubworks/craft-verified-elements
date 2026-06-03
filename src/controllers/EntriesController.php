<?php /** @noinspection PhpUnused */

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use DateInterval;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Handles rendering and data endpoints for the Verified Entries index and entry verification modals.
 */
class EntriesController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    /**
     * Renders the Verified Entries index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate(VerifiedEntries::HANDLE . '/index.twig');
    }

    /**
     * Renders the verification period selection modal.
     *
     * @return Response
     */
    public function actionRequestPeriod(): Response
    {
        $periodOptions = VerifiedEntries::getInstance()
            ->getVerification()
            ->getPeriodOptionsWithCustomDate();

        return $this->asCpModal()
            ->action(VerifiedEntries::HANDLE . '/entries/resolve-date')
            ->contentTemplate(VerifiedEntries::HANDLE . '/_modals/_period.twig', [
                'periodOptions' => $periodOptions,
            ]);
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
            $interval = new DateInterval($verificationPeriod);
            $date = DateTimeHelper::now()->add($interval);
        }

        if ($date === false) {
            return $this->asFailure(Craft::t(VerifiedEntries::HANDLE, 'Not a valid date.'));
        }

        return $this->asJson([
            'date' => $date?->format('Y-m-d'),
        ]);
    }

    /**
     * Returns paginated and sorted entry data for the admin table.
     *
     * @param int|null $userId
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionTableData(?int $userId = null): Response
    {
        $this->requireAcceptsJson();

        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);
        $orderBy = match ($this->request->getParam('sort.0.field')) {
            '__slot:handle' => 'sectionHandle',
            'isVerified' => 'verifiedUntilDate',
            'siteName' => 'siteName',
            default => 'sectionName',
        };

        $sortDir = match ($this->request->getParam('sort.0.direction')) {
            'desc' => SORT_DESC,
            default => SORT_ASC,
        };

        [$results, $total] = VerifiedEntries::getInstance()->getReviewers()->getPaginatedEntries(
            $page,
            $limit,
            $sortDir,
            $orderBy,
            $userId,
        );

        $pagination = AdminTable::paginationLinks($page, $total, $limit);

        return $this->asJson([
            'pagination' => $pagination,
            'data' => $results,
        ]);
    }
}
