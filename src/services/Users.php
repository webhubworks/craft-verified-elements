<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use webhubworks\verifiedentries\db\Queries;
use yii\base\Component;

/**
 * User service
 */
class Users extends Component
{
    /**
     * Get all sections assigned to a reviewer.
     *
     * @param int|null $userId
     * @return array
     */
    public function getSections(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getId();
        }

        $sections = Queries::sectionsByReviewer($userId)->all();

        return array_map(static function ($section) {
            return [
                ...$section,
                'defaultPeriod' => DateTimeHelper::humanDuration($section['defaultPeriod']),
                'url' => $section['type'] == 'single'
                    ? UrlHelper::cpUrl('entries/singles')
                    : UrlHelper::cpUrl('entries/' . $section['handle'], ['filters' => Verification::getFilterParams()]),

            ];
        }, $sections);
    }

    /**
     * Get all entries assigned to a reviewer.
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

        $entries = Queries::entriesByReviewer($userId, $siteId)->all();

        return $this->transformEntries($entries);
    }

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

        $query = Queries::entriesByReviewer($userId, $siteId)->orderBy([$orderBy => $sortDir]);

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        $entries = $this->transformEntries($query->all());

        return [$entries, $total];
    }

    private function transformEntries(array $entries): array
    {
        $formatter = new Formatter();

        return array_map(function ($entry) use ($formatter) {
            $verifiedUntilDate = DateTimeHelper::toDateTime($entry['verifiedUntilDate']);

            $isVerified = $verifiedUntilDate && $verifiedUntilDate > new \DateTime();

            $uri = sprintf("%s/%s/%s-%s",
                'entries',
                $entry['sectionHandle'],
                $entry['entryId'],
                $entry['slug']
            );

            return [
                ...$entry,
                'isVerified' => $isVerified ? 'Verified' : 'Expired',
                'verifiedUntilDate' => $verifiedUntilDate ? $formatter->asDate($verifiedUntilDate) : 'Indefinitely',
                'dateUpdated' => $formatter->asDate(DateTimeHelper::toDateTime($entry['dateUpdated'])),
                'url' => UrlHelper::cpUrl($uri, ['site' => $entry['siteHandle']]),
            ];
        }, $entries);
    }
}
