<?php

use Gibbon\Data\Validator;
use Gibbon\Services\ModuleLoader;
use Gibbon\Session\TokenHandler;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\Reports\Domain\ReportingValueGateway;
use Gibbon\Module\CoursesAndClasses\Domain\CourseGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Module\Transcripts\Services\TranscriptService;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$fail = function (string $message, int $status = 422) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail(__('Your request failed because you do not have access to this action.'), 405);
}

// Not a *Process.php file, so gibbon.php does not check the CSRF token for us.
if (!$container->get(TokenHandler::class)->validateCsrfToken()) {
    $fail(__('Your session has expired. Reload the page and try again.'), 403);
}

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_gradeAjax.php') == false
    || getTranscriptViewAction($guid, $connection2) !== 'Transcripts_all') {
    $fail(__('Your request failed because you do not have access to this action.'), 403);
}

$transcriptGateway = $container->get(TranscriptGateway::class);
$gibbonPersonIDViewer = (int)$session->get('gibbonPersonID');
$gibbonPersonID = (int)($_POST['gibbonPersonID'] ?? 0);
$action = $_POST['action'] ?? '';

$denialReason = getTranscriptAccessDenialReason($pdo, 'Transcripts_all', $gibbonPersonIDViewer, $gibbonPersonID, (int)$session->get('gibbonSchoolYearID'));
if ($denialReason !== null) {
    $fail($denialReason, 403);
}

if ($action === 'saveGrade') {
    $gibbonCourseClassID = (int)($_POST['gibbonCourseClassID'] ?? 0);
    [$criteriaPart, $gradePart] = array_pad(explode(':', (string)($_POST['grade'] ?? ''), 2), 2, '');
    $gibbonReportingCriteriaID = (int)$criteriaPart;
    $gibbonScaleGradeID = (int)$gradePart;

    $criterion = $gibbonReportingCriteriaID > 0 && $gibbonCourseClassID > 0
        ? $transcriptGateway->getGradeCriterionForClass($gibbonReportingCriteriaID, $gibbonCourseClassID)
        : null;
    if (empty($criterion)) {
        $fail(__('This grade cannot be saved because the class has no matching Grade Scale criterion in Reports.'));
    }

    if (!$transcriptGateway->isStudentLinkedToClass($gibbonPersonID, $gibbonCourseClassID)) {
        $fail(__('This student is not enrolled in the selected class.'));
    }

    if ($gibbonScaleGradeID > 0 && !$transcriptGateway->scaleGradeBelongsToScale($gibbonScaleGradeID, (int)$criterion['gibbonScaleID'])) {
        $fail(__('The selected grade does not belong to this criterion\'s grade scale.'));
    }

    $container->get(ModuleLoader::class)->registerModuleNamespace('Reports');
    $reportingValueGateway = $container->get(ReportingValueGateway::class);

    // Mirrors Reports/reporting_writeProcess.php for a Grade Scale criterion.
    $data = [
        'gibbonReportingCycleID'    => $criterion['gibbonReportingCycleID'],
        'gibbonReportingCriteriaID' => $gibbonReportingCriteriaID,
        'gibbonSchoolYearID'        => $criterion['gibbonSchoolYearID'],
        'gibbonCourseClassID'       => $gibbonCourseClassID,
        'gibbonPersonIDStudent'     => $gibbonPersonID,
        'gibbonPersonIDCreated'     => $gibbonPersonIDViewer,
        'value'                     => $gibbonScaleGradeID > 0 ? $reportingValueGateway->getGradeScaleValueByID($gibbonScaleGradeID) : null,
        'comment'                   => null,
        'gibbonScaleGradeID'        => $gibbonScaleGradeID > 0 ? $gibbonScaleGradeID : null,
    ];

    $reportingValueGateway->insertAndUpdate($data, [
        'value' => $data['value'],
        'comment' => $data['comment'],
        'gibbonScaleGradeID' => $data['gibbonScaleGradeID'],
        'gibbonPersonIDModified' => $gibbonPersonIDViewer,
        'timestampModified' => date('Y-m-d H:i:s'),
    ]);

    $saved = $transcriptGateway->getStudentGradeRecordByKey($gibbonPersonID, $gibbonReportingCriteriaID, $gibbonCourseClassID);
    if (empty($saved) || (int)($saved['gibbonScaleGradeID'] ?? 0) !== $gibbonScaleGradeID) {
        $fail(__('The grade could not be saved.'), 500);
    }

    if (($_POST['reload'] ?? 'N') === 'Y') {
        header('HX-Refresh: true');
        exit;
    }

    $scaleCache = [];
    $choices = buildTranscriptGradeChoices($transcriptGateway, [$saved], $scaleCache);
    $context = [
        'ajaxURL' => $session->get('absoluteURL').'/modules/'.basename(__DIR__).'/transcripts_gradeAjax.php'.(!empty($_GET['gibbonStudentProgramHistoryID']) ? '?gibbonStudentProgramHistoryID='.(int)$_GET['gibbonStudentProgramHistoryID'] : ''),
        'csrftoken' => $session->get('csrftoken'),
        'gibbonPersonID' => $gibbonPersonID,
    ];

    echo renderTranscriptGradeCell($saved, $choices, $context);

    $programGateway = $container->get(StudentProgramGateway::class);
    $selectedProgram = resolveTranscriptsProgram($programGateway->getAllProgramsByPerson($gibbonPersonID), (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0));
    $transcriptData = (new TranscriptService($transcriptGateway, $programGateway))->generateStudentTranscript($gibbonPersonID, $selectedProgram);

    $printUrl = $session->get('absoluteURL').'/modules/'.basename(__DIR__).'/transcript_print.php?gibbonPersonID='.$gibbonPersonID;
    if (!empty($selectedProgram['gibbonStudentProgramHistoryID'])) {
        $printUrl .= '&gibbonStudentProgramHistoryID='.(int)$selectedProgram['gibbonStudentProgramHistoryID'];
    }
    $isOfficial = canGenerateOfficialTranscript($guid, $connection2, $container->get(SettingGateway::class));

    echo renderTranscriptSummary($transcriptData, $selectedProgram, $printUrl, $isOfficial, true);
    exit;
}

if ($action === 'saveCatalog') {
    $field = $_POST['field'] ?? '';
    if ($field !== 'credits') {
        $fail(__('Unknown field.'));
    }

    registerCoursesAndClassesAutoloader($session->get('absolutePath'));

    try {
        $changed = $container->get(CourseGateway::class)->upsertCourseCatalog((string)($_POST['courseCode'] ?? ''), [$field => $_POST['value'] ?? '']);
    } catch (\InvalidArgumentException $e) {
        $fail($e->getMessage());
    }

    if ($changed) {
        header('HX-Refresh: true');
    }
    exit;
}

if ($action === 'setupTerm') {
    $gibbonSchoolYearTermID = (int)($_POST['gibbonSchoolYearTermID'] ?? 0);
    $gibbonCourseClassID = (int)($_POST['gibbonCourseClassID'] ?? 0);

    $class = $transcriptGateway->getClassCourseAndYear($gibbonCourseClassID);
    if (empty($class) || !$transcriptGateway->isStudentLinkedToClass($gibbonPersonID, $gibbonCourseClassID)) {
        $fail(__('This student is not enrolled in the selected class.'));
    }
    $termIDs = array_map('intval', array_keys($transcriptGateway->getTermsBySchoolYear((int)$class['gibbonSchoolYearID'])));
    if (!in_array($gibbonSchoolYearTermID, $termIDs, true)) {
        $fail(__('The selected term is not in this class\'s school year.'));
    }

    if ($transcriptGateway->ensureReportingCycleForTerm($gibbonSchoolYearTermID, (int)$class['gibbonCourseID']) <= 0) {
        $fail(__('Grading could not be set up for this term. Check that an active grade scale exists.'));
    }

    header('HX-Refresh: true');
    exit;
}

$fail(__('Unknown action.'));
