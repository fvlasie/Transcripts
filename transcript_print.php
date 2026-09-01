<?php

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;
use Gibbon\Module\Transcripts\Services\TranscriptService;
use Gibbon\Module\Transcripts\Services\TranscriptPdfService;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

checkAndMigrateTranscriptsSettings($pdo);

$returnPath = $session->get('absoluteURL').'/index.php?q=/modules/Transcripts/transcripts_view.php';

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcript_print.php') == false) {
    header("Location: {$returnPath}&return=error0");
    exit;
}

$highestAction = getTranscriptViewAction($guid, $connection2);

if (empty($highestAction)) {
    header("Location: {$returnPath}&return=error0");
    exit;
}

$gibbonSchoolYearID = (int)$session->get('gibbonSchoolYearID');
$gibbonPersonIDViewer = (int)$session->get('gibbonPersonID');
$gibbonPersonID = ($highestAction === 'Generate Transcripts_myTranscript')
    ? $gibbonPersonIDViewer
    : (int)($_GET['gibbonPersonID'] ?? 0);
$gibbonStudentProgramHistoryID = (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0);

if ($gibbonPersonID <= 0) {
    header("Location: {$returnPath}&return=error1");
    exit;
}

if (!canViewStudentTranscript($pdo, $highestAction, $gibbonPersonIDViewer, $gibbonPersonID, $gibbonSchoolYearID)) {
    header("Location: {$returnPath}&return=error0");
    exit;
}

try {
    $transcriptGateway = $container->get(TranscriptGateway::class);
    $programGateway = $container->get(StudentProgramGateway::class);
    $transcriptService = new TranscriptService($transcriptGateway, $programGateway, $container->get(CourseProgramGateway::class));

    $pdfService = new TranscriptPdfService(
        $transcriptService,
        $programGateway,
        $container->get(UserGateway::class),
        $container->get(SettingGateway::class),
        $container->get(Connection::class),
        $session->get('absolutePath'),
        __DIR__
    );

    $settingGateway = $container->get(SettingGateway::class);
    $isOfficial = canGenerateOfficialTranscript($guid, $connection2, $settingGateway);

    $pdfContent = $pdfService->generate($gibbonPersonID, $isOfficial, $gibbonStudentProgramHistoryID);
    $filename = $pdfService->buildFilename($gibbonPersonID, $isOfficial, $gibbonStudentProgramHistoryID);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.htmlentities($filename).'"');
    header('Content-Length: '.strlen($pdfContent));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $pdfContent;
    exit;
} catch (Exception $e) {
    $failPath = "{$returnPath}&gibbonPersonID={$gibbonPersonID}&return=error2";
    if ($gibbonStudentProgramHistoryID > 0) {
        $failPath .= "&gibbonStudentProgramHistoryID={$gibbonStudentProgramHistoryID}";
    }
    header("Location: {$failPath}");
    exit;
}
