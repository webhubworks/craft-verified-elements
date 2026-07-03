<?php

namespace webhubworks\verifiedelements\services\singletons;

use Craft;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\models\ElementData;
use yii\base\Component;

/**
 * The Reviewer service represents logic related to Craft Users who get assigned to review elements.
 */
class Reviewers extends Component
{
    /**
     * Get all elements assigned to a Reviewer, but paginate the results.
     *
     * @param int $page
     * @param int $limit
     * @param int $sortDir
     * @param string $orderBy
     * @param int|null $userId
     * @param int|null $siteId
     * @param string[]|null $elementTypes Element FQCNs to include; null means every element type
     * the current edition enables.
     * @return array{0: ElementData[], 1: int} A tuple of [page of elements, total count across all pages]
     */
    public function getPaginatedElements(
        int    $page,
        int    $limit,
        int    $sortDir = SORT_ASC,
        string $orderBy = 'verifiedUntilDate',
        ?int   $userId = null,
        ?int   $siteId = null,
        ?array $elementTypes = null,
    ): array
    {
        if ($userId === null) {
            $userId = Craft::$app->getUser()->getId();
        }

        $offset = ($page - 1) * $limit;

        $query = PluginQuery::elementsByReviewer($userId, $elementTypes ?? ElementType::enabledTypes(), $siteId)
            ->orderBy([$orderBy => $sortDir]);

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        /** @var ElementData[] $elements */
        $elements = array_map(ElementData::fromArray(...), $query->all());

        return [$elements, $total];
    }
}
