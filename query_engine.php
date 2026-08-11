<?php
use Gibbon\Module\Transcripts\Services\RegistrarQueryService;
use Gibbon\Module\Transcripts\Gateway\RegistrarQueryGateway;

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/query_engine.php') == false) {
    echo "<div class='error'>" . __('You do not have access to this action.') . "</div>";
} else {
    echo "<h2>" . __('Advanced Registrar Query Engine') . "</h2>";

    $page->breadcrumbs->add(__('Registrar Reports'));

    $filters = [
        'programType' => $_GET['programType'] ?? '',
        'concentration' => $_GET['concentration'] ?? '',
        'studentLevel' => $_GET['studentLevel'] ?? '',
        'modeOfInstruction' => $_GET['modeOfInstruction'] ?? '',
        'gender' => $_GET['gender'] ?? '',
    ];

    echo "<form method='GET' action='index.php' class='p-4 bg-gray-50 border rounded mb-4 grid grid-cols-3 gap-4'>";
    echo "<input type='hidden' name='q' value='/modules/Transcripts/query_engine.php'>";

    // Program Filter
    echo "<div><label class='block text-sm font-medium'>" . __('Program Type') . "</label>";
    echo "<select name='programType' class='w-full border p-1 rounded'>";
    echo "<option value=''>" . __('All Programs') . "</option>";
    foreach (['MTS', 'BTh', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree'] as $p) {
        $sel = ($filters['programType'] === $p) ? 'selected' : '';
        echo "<option value='{$p}' {$sel}>{$p}</option>";
    }
    echo "</select></div>";

    // Concentration Filter
    echo "<div><label class='block text-sm font-medium'>" . __('Concentration') . "</label>";
    echo "<select name='concentration' class='w-full border p-1 rounded'>";
    echo "<option value=''>" . __('All Concentrations') . "</option>";
    foreach (['Biblical', 'Professional', 'General', 'Certificate', 'Masters'] as $c) {
        $sel = ($filters['concentration'] === $c) ? 'selected' : '';
        echo "<option value='{$c}' {$sel}>{$c}</option>";
    }
    echo "</select></div>";

    // Student Level Filter
    echo "<div><label class='block text-sm font-medium'>" . __('Student Level') . "</label>";
    echo "<select name='studentLevel' class='w-full border p-1 rounded'>";
    echo "<option value=''>" . __('All Levels') . "</option>";
    foreach (['Freshman', 'Sophomore', 'Junior', 'Senior', 'M1', 'M2', 'M3'] as $l) {
        $sel = ($filters['studentLevel'] === $l) ? 'selected' : '';
        echo "<option value='{$l}' {$sel}>{$l}</option>";
    }
    echo "</select></div>";

    // Mode Filter
    echo "<div><label class='block text-sm font-medium'>" . __('Mode of Instruction') . "</label>";
    echo "<select name='modeOfInstruction' class='w-full border p-1 rounded'>";
    echo "<option value=''>" . __('All Modes') . "</option>";
    foreach (['In-person', 'Remote'] as $m) {
        $sel = ($filters['modeOfInstruction'] === $m) ? 'selected' : '';
        echo "<option value='{$m}' {$sel}>{$m}</option>";
    }
    echo "</select></div>";

    // Gender Filter
    echo "<div><label class='block text-sm font-medium'>" . __('Sex / Gender') . "</label>";
    echo "<select name='gender' class='w-full border p-1 rounded'>";
    echo "<option value=''>" . __('All') . "</option>";
    foreach (['M' => 'Male', 'F' => 'Female'] as $k => $g) {
        $sel = ($filters['gender'] === $k) ? 'selected' : '';
        echo "<option value='{$k}' {$sel}>{$g}</option>";
    }
    echo "</select></div>";

    echo "<div class='flex items-end'><button type='submit' class='btn btn-primary w-full'>" . __('Filter Records') . "</button></div>";
    echo "</form>";

    // Execute query
    $queryGateway = new RegistrarQueryGateway($pdo);
    $queryService = new RegistrarQueryService($queryGateway);
    $activeFilters = array_filter($filters);
    $report = $queryService->queryRecords($activeFilters);

    echo "<div class='mb-2 text-sm text-gray-600'>" . sprintf(__('Showing %d total course entries across %d students.'), $report['summary']['totalResults'], $report['summary']['totalStudents']) . "</div>";

    echo "<table class='w-full border-collapse border border-gray-300'>";
    echo "<thead><tr class='bg-gray-200'>";
    echo "<th class='p-2 border'>" . __('Student Name') . "</th>";
    echo "<th class='p-2 border'>" . __('Program') . "</th>";
    echo "<th class='p-2 border'>" . __('Concentration') . "</th>";
    echo "<th class='p-2 border'>" . __('Level') . "</th>";
    echo "<th class='p-2 border'>" . __('Course') . "</th>";
    echo "<th class='p-2 border'>" . __('Mode') . "</th>";
    echo "</tr></thead><tbody>";

    if (empty($report['data'])) {
        echo "<tr><td colspan='6' class='p-4 text-center'>" . __('No records match the selected criteria.') . "</td></tr>";
    } else {
        foreach ($report['data'] as $row) {
            echo "<tr>";
            echo "<td class='p-2 border'>{$row['surname']}, {$row['firstName']}</td>";
            echo "<td class='p-2 border'>{$row['programType']}</td>";
            echo "<td class='p-2 border'>{$row['concentration']}</td>";
            echo "<td class='p-2 border'>{$row['studentLevel']}</td>";
            echo "<td class='p-2 border'>{$row['courseCode']} - {$row['courseName']}</td>";
            echo "<td class='p-2 border'>{$row['modeOfInstruction']}</td>";
            echo "</tr>";
        }
    }
    echo "</tbody></table>";
}
