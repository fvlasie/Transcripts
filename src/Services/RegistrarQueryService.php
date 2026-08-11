<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Module\Transcripts\Gateway\RegistrarQueryGateway;

/**
 * Service to execute dynamic filtering, sorting, and cross-sectional analytics across student cohorts.
 */
class RegistrarQueryService
{
    private RegistrarQueryGateway $queryGateway;

    public function __construct(RegistrarQueryGateway $queryGateway)
    {
        $this->queryGateway = $queryGateway;
    }

    public function queryRecords(array $filters = [], array $sorting = []): array
    {
        $results = $this->queryGateway->queryStudentRecords($filters, $sorting);

        // Calculate analytical summary metadata
        $totalStudents = count(array_unique(array_column($results, 'gibbonPersonID')));
        
        return [
            'summary' => [
                'totalResults' => count($results),
                'totalStudents' => $totalStudents,
                'appliedFilters' => $filters,
            ],
            'data' => $results
        ];
    }
}
