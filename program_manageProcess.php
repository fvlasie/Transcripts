<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$moduleName = getModuleName($_POST['address'] ?? '');

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error0'));
    exit;
}

$intent = $_POST['intent'] ?? '';
if ($intent === 'addCohort') {
    $listURL = Url::fromModuleRoute($moduleName, 'program_manage.php');
    $gibbonSchoolYearID = (int) ($_POST['gibbonSchoolYearID'] ?? 0);
    $gibbonYearGroupID = (int) ($_POST['gibbonYearGroupID'] ?? 0);
    $gender = in_array($_POST['gender'] ?? '', ['M', 'F', 'Other', 'Unspecified'], true) ? $_POST['gender'] : '';
    $personIDs = array_filter(array_map('intval', (array) ($_POST['gibbonPersonID'] ?? [])));
    $programType = $_POST['programType'] ?? '';
    $startDate = Format::dateConvert($_POST['startDate'] ?? '');
    $status = $_POST['status'] ?? '';

    $programGateway = $container->get(StudentProgramGateway::class);
    $cohortURL = Url::fromModuleRoute($moduleName, 'program_manage_add.php')->withQueryParams([
        'cohort' => '1',
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'gibbonYearGroupID' => $gibbonYearGroupID,
        'gender' => $gender,
    ]);
    if ($gibbonSchoolYearID <= 0) {
        header('Location: '.$cohortURL->withQueryParam('return', 'error1'));
        exit;
    }
    if (empty($personIDs)) {
        header('Location: '.$cohortURL->withQueryParam('return', 'error7'));
        exit;
    }
    if ($programType === '' || !$programGateway->programTypeExists($programType)) {
        header('Location: '.$cohortURL->withQueryParam('return', 'error8'));
        exit;
    }
    if (empty($startDate)) {
        header('Location: '.$cohortURL->withQueryParam('return', 'error9'));
        exit;
    }
    if ($status === '') {
        header('Location: '.$cohortURL->withQueryParam('return', 'error10'));
        exit;
    }

    $allowed = array_column($programGateway->selectCohortStudents($gibbonSchoolYearID, $gibbonYearGroupID, $gender), 'gibbonPersonID');
    try {
        $result = $programGateway->addProgramsForPeople(
            $personIDs,
            $allowed,
            $programType,
            $startDate,
            $status,
            $_POST['notes'] ?? null
        );
    } catch (\InvalidArgumentException $e) {
        header('Location: '.$cohortURL->withQueryParam('return', 'error1'));
        exit;
    }

    header('Location: '.$listURL->withQueryParams([
        'programsAdded' => $result['added'],
        'programsSkipped' => $result['skipped'],
    ]));
    exit;
}

$gibbonPersonID = (int)($_POST['gibbonPersonID'] ?? 0);
$programType = $_POST['programType'] ?? '';
$startDate = $_POST['startDate'] ?? '';
$status = $_POST['status'] ?? '';

$programGateway = $container->get(StudentProgramGateway::class);
$filterGibbonPersonID = (int)($_POST['filterGibbonPersonID'] ?? 0);
$addURL = Url::fromModuleRoute($moduleName, 'program_manage_add.php');
if ($filterGibbonPersonID > 0) {
    $addURL = $addURL->withQueryParam('gibbonPersonID', $filterGibbonPersonID);
}
if ($gibbonPersonID <= 0) {
    header('Location: '.$addURL->withQueryParam('return', 'error7'));
    exit;
}
if ($programType == '' || !$programGateway->programTypeExists($programType)) {
    header('Location: '.$addURL->withQueryParam('return', 'error8'));
    exit;
}
if ($startDate == '') {
    header('Location: '.$addURL->withQueryParam('return', 'error9'));
    exit;
}
if ($status == '') {
    header('Location: '.$addURL->withQueryParam('return', 'error10'));
    exit;
}

$data = [
    'gibbonPersonID' => $gibbonPersonID,
    'programType' => $programType,
    'startDate' => Format::dateConvert($startDate),
    'switchDate' => !empty($_POST['switchDate']) ? Format::dateConvert($_POST['switchDate']) : null,
    'graduationDate' => !empty($_POST['graduationDate']) ? Format::dateConvert($_POST['graduationDate']) : null,
    'status' => $status,
    'notes' => $_POST['notes'] ?? null,
];

try {
    $programGateway->addProgramHistory($data);

    $redirectParams = ['return' => 'success0'];
    if ($filterGibbonPersonID > 0) {
        $redirectParams['gibbonPersonID'] = $filterGibbonPersonID;
    }

    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParams($redirectParams));
} catch (Exception $e) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error2'));
}
