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
        $code = 'error1';
        if ($e->getMessage() === 'date') {
            $code = 'error6';
        } elseif ($e->getMessage() === 'program') {
            $code = 'error8';
        }
        $redirect['return'] = $code;
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

$editURL = Url::fromModuleRoute($moduleName, 'program_manage_edit.php')->withQueryParams([
    'gibbonStudentProgramHistoryID' => $gibbonStudentProgramHistoryID,
]);
if ($filterGibbonPersonID > 0) {
    $editURL = $editURL->withQueryParam('gibbonPersonID', $filterGibbonPersonID);
}
if ($gibbonStudentProgramHistoryID <= 0) {
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParam('return', 'error1'));
    exit;
}
if ($programType == '' || !$programGateway->programTypeExists($programType)) {
    header('Location: '.$editURL->withQueryParam('return', 'error8'));
    exit;
}
if ($startDate == '') {
    header('Location: '.$editURL->withQueryParam('return', 'error9'));
    exit;
}
if ($status == '') {
    header('Location: '.$editURL->withQueryParam('return', 'error10'));
    exit;
}

try {
    $existing = $programGateway->getByID($gibbonStudentProgramHistoryID);

    if (empty($existing)) {
        header('Location: '.$editURL->withQueryParam('return', 'error1'));
        exit;
    }

    if ($gibbonPersonID <= 0) {
        $gibbonPersonID = (int)($existing['gibbonPersonID'] ?? 0);
    }

    if ($gibbonPersonID <= 0) {
        header('Location: '.$editURL->withQueryParam('return', 'error7'));
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
    $fail = ['return' => 'error2'];
    if ($filterGibbonPersonID > 0) {
        $fail['gibbonPersonID'] = $filterGibbonPersonID;
    }
    header('Location: '.Url::fromModuleRoute($moduleName, 'program_manage.php')->withQueryParams($fail));
}
