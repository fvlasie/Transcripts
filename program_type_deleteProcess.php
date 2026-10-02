<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

include '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
$name = $_POST['name'] ?? '';
$deleteURL = Url::fromModuleRoute('Transcripts', 'program_type_delete.php')->withQueryParam('name', $name);
$listURL = Url::fromModuleRoute('Transcripts', 'program_manage.php');
if ($gibbonPersonID !== '') {
    $deleteURL = $deleteURL->withQueryParam('gibbonPersonID', $gibbonPersonID);
    $listURL = $listURL->withQueryParam('gibbonPersonID', $gibbonPersonID);
}

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_type_delete.php') == false) {
    header('Location: '.$deleteURL->withReturn('error0'));
    exit;
}

$removed = $container->get(StudentProgramGateway::class)->deleteProgramType($name);
header('Location: '.($removed ? $listURL->withReturn('success3') : $listURL->withReturn('error5')));
