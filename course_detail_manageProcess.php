<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonSchoolYearID = (int) ($_POST['gibbonSchoolYearID'] ?? 0);
$URL = Url::fromModuleRoute('Transcripts', 'course_detail_manage.php')->withQueryParam('gibbonSchoolYearID', $gibbonSchoolYearID);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_detail_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

$courses = $pdo->select(
    'SELECT gibbonCourseID FROM gibbonCourse WHERE gibbonSchoolYearID = :gibbonSchoolYearID',
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchAll();

$areas = $pdo->select(
    "SELECT gibbonDepartmentID FROM gibbonDepartment WHERE type = 'Learning Area'"
)->fetchAll();
$areaIDs = array_map('intval', array_column($areas, 'gibbonDepartmentID'));
$levels = getTranscriptsCourseLevels();
$modes = getTranscriptsInstructionModes();
$skipped = false;

foreach ($courses as $course) {
    $courseID = (int) $course['gibbonCourseID'];
    $level = (string) ($_POST['courseLevel'.$courseID] ?? '');
    $mode = (string) ($_POST['modeOfInstruction'.$courseID] ?? '');
    $departmentID = (int) ($_POST['gibbonDepartmentID'.$courseID] ?? 0);
    if (!isset($levels[$level]) || !isset($modes[$mode]) || ($departmentID > 0 && !in_array($departmentID, $areaIDs, true))) {
        $skipped = true;
        continue;
    }

    $pdo->statement(
        'UPDATE gibbonCourse
         SET courseLevel = :courseLevel, modeOfInstruction = :modeOfInstruction, gibbonDepartmentID = :gibbonDepartmentID
         WHERE gibbonCourseID = :gibbonCourseID AND gibbonSchoolYearID = :gibbonSchoolYearID',
        [
            'courseLevel' => $level,
            'modeOfInstruction' => $mode,
            'gibbonDepartmentID' => $departmentID > 0 ? $departmentID : null,
            'gibbonCourseID' => $courseID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ]
    );
}

header('Location: '.$URL->withReturn($skipped ? 'warning1' : 'success0'));
