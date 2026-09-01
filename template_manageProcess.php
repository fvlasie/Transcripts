<?php

use Gibbon\Data\Validator;
use Gibbon\FileUploader;
use Gibbon\Domain\System\SettingGateway;

include '../../gibbon.php';

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSettings($pdo);

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/template_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/template_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$uploadFail = false;

$customAssetPath = '/'.trim($_POST['customAssetPath'] ?? '/uploads/transcripts', '/');
upsertTranscriptsSetting($pdo, 'customAssetPath', $customAssetPath);

$registrarPersonID = trim((string)($_POST['registrarGibbonPersonID'] ?? ''));
upsertTranscriptsSetting($pdo, 'registrarGibbonPersonID', $registrarPersonID !== '' ? (string)((int)$registrarPersonID) : '');

$destinationFolder = ltrim($customAssetPath, '/');
$fileUploader = new FileUploader($pdo, $session);

$uploadMap = [
    'page1BackgroundFile' => 'page1BackgroundPath',
    'page2BackgroundFile' => 'page2BackgroundPath',
    'registrarSignatureFile' => 'registrarSignaturePath',
];

foreach ($uploadMap as $fileField => $settingName) {
    if (empty($_FILES[$fileField]['tmp_name'])) {
        continue;
    }

    if ($fileField === 'registrarSignatureFile') {
        $fileUploader->getFileExtensions('Graphics/Design');
    } else {
        $fileUploader->getFileExtensions('Document');
    }

    $uploadedPath = $fileUploader->upload(
        $_FILES[$fileField]['name'],
        $_FILES[$fileField]['tmp_name'],
        $destinationFolder
    );

    if (empty($uploadedPath)) {
        $uploadFail = true;
    } else {
        $settingGateway->updateSettingByScope('Transcripts', $settingName, '/'.$uploadedPath);
    }
}

$URL .= $uploadFail ? '&return=warning1' : '&return=success0';
header("Location: {$URL}");
