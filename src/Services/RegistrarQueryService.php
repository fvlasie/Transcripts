<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\Transcripts\Domain\RegistrarQueryGateway;

class RegistrarQueryService
{
    private RegistrarQueryGateway $queryGateway;

    public function __construct(RegistrarQueryGateway $queryGateway)
    {
        $this->queryGateway = $queryGateway;
    }

    public function queryRecords(QueryCriteria $criteria): array
    {
        $results = $this->queryGateway->queryStudentRecords($criteria);

        return [
            'summary' => [
                'totalResults' => $results->getResultCount(),
                'totalStudents' => $this->queryGateway->countDistinctStudents($criteria),
                'appliedFilters' => $criteria->getFilterBy(),
            ],
            'data' => $results,
        ];
    }
}
