<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use DateTime;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\VerifiedEntries;
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
        $filters = VerifiedEntries::getInstance()->getVerification()->getFilterParams();

        return array_map(static function ($section) use ($filters) {
            return [
                ...$section,
                'defaultPeriod' => DateTimeHelper::humanDuration($section['defaultPeriod']),
                'url' => $section['type'] == 'single'
                    ? UrlHelper::cpUrl('entries/singles')
                    : UrlHelper::cpUrl(
                        'entries/' . $section['handle'],
                        ['filters' => $filters]
                    ),

            ];
        }, $sections);
    }

    /**
     * TODO: is this being used anywhere? Could it be deleted?
     *
     * Get all entries assigned to a Reviewer.
     *
     * @param int|null $userId
     * @param int|null $siteId
     * @return array
     */
    public function getEntries(?int $userId = null, ?int $siteId = null): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getId();
        }

        $entries = PluginQuery::entriesByReviewer($userId, $siteId)->all();

        return $this->transformEntries($entries);
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
     * @return array
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

        $entries = $this->transformEntries($query->all());

        return [$entries, $total];
    }


    // HELPERS
    // =============================================================================================

    /**
     * Transform the result of the query to an array of entries.
     *
     * @param array $entries
     * @return array
     * TODO address the toDateTime exception that could be thrown
     */
    private function transformEntries(array $entries): array
    {
        return array_map(static function ($entry) {
            $formatter = new Formatter();
            $verifiedUntilDate = DateTimeHelper::toDateTime($entry['verifiedUntilDate']);

            $isVerified = $verifiedUntilDate && $verifiedUntilDate > new DateTime();

            $uri = sprintf("%s/%s/%s-%s",
                'entries',
                $entry['sectionHandle'],
                $entry['entryId'],
                $entry['slug']
            );

            return [
                ...$entry,
                'isVerified' => $isVerified ? 'Verified' : 'Expired',
                'verifiedUntilDate' => VerifiedEntries::getInstance()
                    ->getVerification()
                    ->makeVerificationDateReadable($verifiedUntilDate),
                'dateUpdated' => $formatter->asDate(DateTimeHelper::toDateTime($entry['dateUpdated'])),
                'url' => UrlHelper::cpUrl($uri, ['site' => $entry['siteHandle']]),
            ];
        }, $entries);
    }
}
