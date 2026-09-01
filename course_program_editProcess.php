<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$moduleName = getModuleName($_POST['address'] ?? '');
$gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? '';
$filterProgramType = $_POST['filterProgramType'] ?? '';
$courseCode = trim((string)($_POST['courseCode'] ?? ''));
$gibbonCourseID = (int)($_POST['gibbonCourseID'] ?? 0);

$redirectParams = [
    'gibbonSchoolYearID' => $gibbonSchoolYearID,
    'programType' => $filterProgramType,
];

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_program.php') == false) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'course_program.php')->withQueryParams($redirectParams + ['return' => 'error0']));
    exit;
}

$courseProgramGateway = $container->get(CourseProgramGateway::class);

if ($courseCode === '' && $gibbonCourseID > 0) {
    $course = $courseProgramGateway->getCourseByID($gibbonCourseID);
    $courseCode = trim((string)($course['courseCode'] ?? ''));
}

if ($courseCode === '') {
    header('Location: '.Url::fromModuleRoute($moduleName, 'course_program.php')->withQueryParams($redirectParams + ['return' => 'error1']));
    exit;
}

$programTypes = $_POST['programTypes'] ?? [];
if (!is_array($programTypes)) {
    $programTypes = $programTypes !== '' ? [$programTypes] : [];
}

$allowed = array_keys(getTranscriptsProgramTypes());
$programTypes = array_values(array_intersect($programTypes, $allowed));

$courseProgramGateway->replaceProgramsForCourseCode($courseCode, $programTypes);

header('Location: '.Url::fromModuleRoute($moduleName, 'course_program.php')->withQueryParams($redirectParams + ['return' => 'success0']));
