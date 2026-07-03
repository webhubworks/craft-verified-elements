<?php

namespace webhubworks\verifiedelements\services\singletons;

use Craft;
use craft\elements\conditions\ElementCondition;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\Section;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedelements\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedelements\enums\DateStatus;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\VerificationPeriod;
use webhubworks\verifiedelements\models\ElementData;
use yii\base\Component;

/**
 * The Reviewer service represents logic related to Craft Users who get assigned to review entries.
 */
class Reviewers extends Component
{
    /**
     * Get all sections where a User is the default Reviewer.
     *
     * @param int|null $userId
     * @return array
     */
    public function getSections(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getId();
        }

        $sections = PluginQuery::sectionsByReviewer($userId)->all();
        $filters = $this->getFilterParams();

        return array_map(static function ($section) use ($filters) {

            if ($section['defaultPeriod'] === VerificationPeriod::Indefinitely->value) {
                $defaultPeriod = DateStatus::Indefinite->label();
            }
            else {
                $defaultPeriod = DateTimeHelper::humanDuration($section['defaultPeriod']);
            }

            if ($section['type'] == Section::TYPE_SINGLE) {
                $url = UrlHelper::cpUrl('entries/singles');
            }
            else {
                $url = UrlHelper::cpUrl(
                    'entries/' . $section['handle'],
                    ['filters' => $filters]
                );
            }

            return [...$section, 'defaultPeriod' => $defaultPeriod, 'url' => $url];

        }, $sections);
    }

    /**
     * Get all elements assigned to a Reviewer, but paginate the results.
     *
     * @param int $page
     * @param int $limit
     * @param int $sortDir
     * @param string $orderBy
     * @param int|null $userId
     * @param int|null $siteId
     * @return array{0: ElementData[], 1: int} A tuple of [page of elements, total count across all pages]
     */
    public function getPaginatedElements(
        int    $page,
        int    $limit,
        int    $sortDir = SORT_ASC,
        string $orderBy = 'verifiedUntilDate',
        ?int   $userId = null,
        ?int   $siteId = null,
    ): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getId();
        }

        $offset = ($page - 1) * $limit;

        $query = PluginQuery::elementsByReviewer($userId, ElementType::enabledTypes(), $siteId)
            ->orderBy([$orderBy => $sortDir]);

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        /** @var ElementData[] $elements */
        $elements = array_map(ElementData::fromArray(...), $query->all());

        return [$elements, $total];
    }

    /**
     * Returns URL query params that filter an element index to show a reviewer's expired elements.
     *
     * @param int|null $reviewerId
     * @param string $elementType
     * @return string The URL query params
     */
    public function getFilterParams(?int $reviewerId = null, string $elementType = Entry::class): string
    {
        /** @var ElementCondition $condition */
        $condition = $elementType::createCondition();

        $verifiedRule = new VerifiedConditionRule();
        $verifiedRule->value = false;
        $condition->addConditionRule($verifiedRule);

        if ($reviewerId !== null) {
            $reviewerRule = new ReviewerConditionRule();
            $reviewerRule->setElementIds([$reviewerId]);
            $condition->addConditionRule($reviewerRule);
        }

        $config = [
            'condition' => $condition->getConfig()
        ];

        return UrlHelper::buildQuery($config);
    }
}
