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

$gibbonStudentProgramHistoryID = (int)($_POST['gibbonStudentProgramHistoryID'] ?? 0);
$filterGibbonPersonID = (int)($_POST['filterGibbonPersonID'] ?? 0);
$programGateway = $container->get(StudentProgramGateway::class);

if (($_POST['intent'] ?? '') === 'switchProgram') {
    $listURL = Url::fromModuleRoute($moduleName, 'program_manage.php');
    $redirect = [];
    if ($filterGibbonPersonID > 0) {
        $redirect['gibbonPersonID'] = $filterGibbonPersonID;
    }

    $switchDate = !empty($_POST['switchDate']) ? Format::dateConvert($_POST['switchDate']) : '';
    try {
        $programGateway->switchProgram(
            $gibbonStudentProgramHistoryID,
            $_POST['programType'] ?? '',
            $switchDate,
            $_POST['notes'] ?? null
        );
        $redirect['return'] = 'success4';
    } catch (\InvalidArgumentException $e) {
        $redirect['return'] = $e->getMessage() === 'date' ? 'error6' : 'error1';
    } catch (Exception $e) {
        $redirect['return'] = 'error2';
    }

    header('Location: '.$listURL->withQueryParams($redirect));
    exit;
}

$gibbonPersonID = (int)($_POST['gibbonPersonID'] ?? 0);
$programType = $_POST['programType'] ?? '';
$startDate = $_POST['startDate'] ?? '';
$status = $_POST['status'] ?? '';

if ($gibbonStudentProgramHistoryID <= 0 || $programType == '' || $startDate == '' || $status == '' || !$programGateway->programTypeExists($programType)) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error1'));
    exit;
}

try {
    $existing = $programGateway->getByID($gibbonStudentProgramHistoryID);

    if (empty($existing)) {
        header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error1'));
        exit;
    }

    if ($gibbonPersonID <= 0) {
        $gibbonPersonID = (int)($existing['gibbonPersonID'] ?? 0);
    }

    if ($gibbonPersonID <= 0) {
        header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error1'));
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

    $programGateway->updateProgramHistory($gibbonStudentProgramHistoryID, $data);

    $redirectParams = ['return' => 'success1'];
    if ($filterGibbonPersonID > 0) {
        $redirectParams['gibbonPersonID'] = $filterGibbonPersonID;
    }

    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParams($redirectParams));
} catch (Exception $e) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error2'));
}
