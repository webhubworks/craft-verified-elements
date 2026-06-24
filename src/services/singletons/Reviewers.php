<?php

namespace webhubworks\verifiedentries\services\singletons;

use Craft;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\Section;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\enums\DateStatus;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\models\ReviewerEntryData;
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
     * Get all entries assigned to a Reviewer, but paginate the results.
     *
     * @param int $page
     * @param int $limit
     * @param int $sortDir
     * @param string $orderBy
     * @param int|null $userId
     * @param int|null $siteId
     * @return array{0: ReviewerEntryData[], 1: int} A tuple of [page of entries, total count across all pages]
     */
    public function getPaginatedEntries(
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

        $query = PluginQuery::entriesByReviewer($userId, $siteId)->orderBy([$orderBy => $sortDir]);

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        /** @var ReviewerEntryData[] $entries */
        $entries = array_map(ReviewerEntryData::fromArray(...), $query->all());

        return [$entries, $total];
    }

    /**
     * Returns URL query params that filter the entry index to show a reviewer's expired entries.
     *
     * @param int|null $reviewerId
     * @return string The URL query params
     */
    public function getFilterParams(?int $reviewerId = null): string
    {
        $condition = new EntryCondition(Entry::class);

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
