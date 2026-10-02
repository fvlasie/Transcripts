<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$addURL = Url::fromModuleRoute('Transcripts', 'program_type_add.php');
$listURL = Url::fromModuleRoute('Transcripts', 'program_manage.php');
if ($gibbonPersonID !== '') {
    $addURL = $addURL->withQueryParam('gibbonPersonID', $gibbonPersonID);
    $listURL = $listURL->withQueryParam('gibbonPersonID', $gibbonPersonID);
}

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_type_add.php') == false) {
    header('Location: '.$addURL->withReturn('error0'));
    exit;
}

try {
    $container->get(StudentProgramGateway::class)->addProgramType($_POST['name'] ?? '');
} catch (\InvalidArgumentException $e) {
    header('Location: '.$addURL->withReturn($e->getMessage() === 'duplicate' ? 'error4' : 'error3'));
    exit;
}

header('Location: '.$listURL->withReturn('success2'));
