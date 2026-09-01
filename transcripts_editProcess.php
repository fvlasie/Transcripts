<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$moduleName = getModuleName($_POST['address'] ?? '');
$gibbonPersonID = (int)($_POST['gibbonPersonID'] ?? 0);
$gibbonCourseClassID = (int)($_POST['gibbonCourseClassID'] ?? 0);
$gibbonReportingValueID = (int)($_POST['gibbonReportingValueID'] ?? 0);
$gibbonSchoolYearTermID = (int)($_POST['gibbonSchoolYearTermID'] ?? 0);
$gibbonReportingCycleID = (int)($_POST['gibbonReportingCycleID'] ?? 0);
$gibbonStudentProgramHistoryID = (int)($_POST['gibbonStudentProgramHistoryID'] ?? 0);

$redirectParams = ['gibbonPersonID' => $gibbonPersonID];
if ($gibbonStudentProgramHistoryID > 0) {
    $redirectParams['gibbonStudentProgramHistoryID'] = $gibbonStudentProgramHistoryID;
}

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_edit.php') == false) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams + ['return' => 'error0']));
    exit;
}

$highestAction = getTranscriptViewAction($guid, $connection2);
if ($highestAction !== 'Generate Transcripts_all') {
    header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams + ['return' => 'error0']));
    exit;
}

if ($gibbonPersonID <= 0 || $gibbonCourseClassID <= 0) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams + ['return' => 'error1']));
    exit;
}

$transcriptGateway = $container->get(TranscriptGateway::class);
$record = $transcriptGateway->getStudentTranscriptRecord($gibbonPersonID, $gibbonCourseClassID, $gibbonSchoolYearTermID, $gibbonReportingValueID);

if (empty($record)) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams + ['return' => 'error1']));
    exit;
}

$isAdd = empty($record['gibbonReportingValueID']);
if ($gibbonSchoolYearTermID > 0) {
    $gibbonReportingCycleID = $transcriptGateway->ensureReportingCycleForTerm($gibbonSchoolYearTermID);
} elseif ($gibbonReportingCycleID <= 0) {
    $gibbonReportingCycleID = (int)($record['gibbonReportingCycleID'] ?? 0);
}
if ($gibbonReportingCycleID > 0) {
    $record['gibbonReportingCycleID'] = $gibbonReportingCycleID;
}
$gibbonScaleGradeID = (int)($_POST['gibbonScaleGradeID'] ?? 0);
$letterGrade = trim((string)($_POST['letterGrade'] ?? ''));

if ($gibbonScaleGradeID > 0) {
    $value = $transcriptGateway->getGradeScaleValueByID($gibbonScaleGradeID);
    $savedID = $transcriptGateway->saveReportingGrade(
        $record,
        $gibbonScaleGradeID,
        $value,
        (int)$session->get('gibbonPersonID')
    );
} elseif ($letterGrade !== '') {
    $savedID = $transcriptGateway->saveReportingGrade(
        $record,
        null,
        $letterGrade,
        (int)$session->get('gibbonPersonID')
    );
} else {
    header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams + ['return' => 'error1']));
    exit;
}

if (empty($savedID)) {
    $redirectParams['return'] = 'error3';
} else {
    $redirectParams['return'] = $isAdd ? 'success2' : 'success1';
}

header('Location: '.Url::fromModuleRoute($moduleName, 'transcripts_view.php')->withQueryParams($redirectParams));
