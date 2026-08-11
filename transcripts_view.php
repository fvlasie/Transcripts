<?php
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Gateway\TranscriptGateway;
use Gibbon\Module\Transcripts\Gateway\StudentProgramGateway;
use Gibbon\Module\Transcripts\Services\TranscriptService;

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_view.php') == false) {
    // Access denied
    echo "<div class='error'>" . __('You do not have access to this action.') . "</div>";
} else {
    echo "<h2>" . __('Student Transcripts') . "</h2>";

    $page->breadcrumbs->add(__('Transcripts'));

    $transcriptGateway = new TranscriptGateway($pdo);
    $programGateway = new StudentProgramGateway($pdo);
    $transcriptService = new TranscriptService($transcriptGateway, $programGateway);

    $gibbonPersonID = $_GET['gibbonPersonID'] ?? $_SESSION[$guid]['gibbonPersonID'];
    $useSecular = isset($_GET['useSecular']) && $_GET['useSecular'] == '1';

    // Student selection dropdown for Admin / Staff
    if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php')) {
        echo "<form method='GET' action='index.php' class='mb-4'>";
        echo "<input type='hidden' name='q' value='/modules/Transcripts/transcripts_view.php'>";
        echo "<label class='inline-block mr-2'>" . __('Select Student:') . "</label>";
        
        // Query students
        $sql = "SELECT gibbonPersonID, preferredName, surname FROM gibbonPerson WHERE status='Full' AND gibbonRoleIDPrimary=(SELECT gibbonRoleID FROM gibbonRole WHERE category='Student' LIMIT 1) ORDER BY surname, preferredName";
        $result = $pdo->executeQuery([], $sql);
        
        echo "<select name='gibbonPersonID' onchange='this.form.submit()'>";
        while ($row = $result->fetch()) {
            $selected = ($row['gibbonPersonID'] == $gibbonPersonID) ? 'selected' : '';
            echo "<option value='{$row['gibbonPersonID']}' {$selected}>{$row['surname']}, {$row['preferredName']}</option>";
        }
        echo "</select>";

        echo "<label class='ml-4 inline-block mr-2'><input type='checkbox' name='useSecular' value='1' " . ($useSecular ? 'checked' : '') . " onchange='this.form.submit()'> " . __('Use Secular Term Names') . "</label>";
        echo "</form>";
    }

    if ($gibbonPersonID) {
        $transcriptData = $transcriptService->generateStudentTranscript((int)$gibbonPersonID, $useSecular);

        echo "<div class='trail mb-4 p-4 bg-gray-100 rounded'>";
        echo "<h4>" . __('Cumulative GPA:') . " <strong>" . renderGpaBadge($transcriptData['cumulativeGPA']) . "</strong></h4>";
        echo "<p>" . __('Total Credits Earned:') . " " . $transcriptData['totalCredits'] . "</p>";
        echo "</div>";

        echo "<h3>" . __('Academic Record') . "</h3>";
        echo "<table class='w-full border-collapse border border-gray-300 mt-2'>";
        echo "<thead><tr class='bg-gray-200'>";
        echo "<th class='p-2 border'>" . __('Year') . "</th>";
        echo "<th class='p-2 border'>" . __('Term') . "</th>";
        echo "<th class='p-2 border'>" . __('Course Code') . "</th>";
        echo "<th class='p-2 border'>" . __('Course Name') . "</th>";
        echo "<th class='p-2 border'>" . __('Level') . "</th>";
        echo "<th class='p-2 border'>" . __('Mode') . "</th>";
        echo "<th class='p-2 border'>" . __('Credits') . "</th>";
        echo "<th class='p-2 border'>" . __('Grade') . "</th>";
        echo "<th class='p-2 border'>" . __('GPA Points') . "</th>";
        echo "</tr></thead><tbody>";

        if (empty($transcriptData['records'])) {
            echo "<tr><td colspan='9' class='p-4 text-center'>" . __('No grade records found.') . "</td></tr>";
        } else {
            foreach ($transcriptData['records'] as $rec) {
                echo "<tr>";
                echo "<td class='p-2 border'>{$rec['schoolYear']}</td>";
                echo "<td class='p-2 border'>{$rec['term']}</td>";
                echo "<td class='p-2 border'>{$rec['courseCode']}</td>";
                echo "<td class='p-2 border'>{$rec['courseName']}</td>";
                echo "<td class='p-2 border'>{$rec['courseLevel']}</td>";
                echo "<td class='p-2 border'>{$rec['modeOfInstruction']}</td>";
                echo "<td class='p-2 border text-center'>{$rec['credits']}</td>";
                echo "<td class='p-2 border text-center'>" . ($rec['letterGrade'] ?? '-') . "</td>";
                echo "<td class='p-2 border text-center'>" . ($rec['gpaPoints'] !== null ? number_format($rec['gpaPoints'], 1) : '-') . "</td>";
                echo "</tr>";
            }
        }
        echo "</tbody></table>";
    }
}
