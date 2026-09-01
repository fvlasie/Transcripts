<?php

namespace Gibbon\Module\Transcripts\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Services\Format;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Generates official transcripts by rendering all text with mPDF, then compositing
 * vector PDF backgrounds with FPDI so artwork stays as vectors for clean print output.
 */
class TranscriptPdfService
{
    private TranscriptService $transcriptService;
    private TranscriptLayoutService $layoutService;
    private StudentProgramGateway $programGateway;
    private UserGateway $userGateway;
    private SettingGateway $settingGateway;
    private Connection $connection;
    private string $absolutePath;
    private string $modulePath;

    public function __construct(
        TranscriptService $transcriptService,
        StudentProgramGateway $programGateway,
        UserGateway $userGateway,
        SettingGateway $settingGateway,
        Connection $connection,
        string $absolutePath,
        string $modulePath,
        ?TranscriptLayoutService $layoutService = null
    ) {
        $this->transcriptService = $transcriptService;
        $this->layoutService = $layoutService ?? new TranscriptLayoutService();
        $this->programGateway = $programGateway;
        $this->userGateway = $userGateway;
        $this->settingGateway = $settingGateway;
        $this->connection = $connection;
        $this->absolutePath = rtrim($absolutePath, '/');
        $this->modulePath = rtrim($modulePath, '/');
    }

    public function generate(int $gibbonPersonID, bool $isOfficial = true, int $gibbonStudentProgramHistoryID = 0): string
    {
        $page1Background = $this->resolveBackgroundPath(
            $this->settingGateway->getSettingByScope('Transcripts', 'page1BackgroundPath'),
            'page1.pdf'
        );
        $page2Background = $this->resolveBackgroundPath(
            $this->settingGateway->getSettingByScope('Transcripts', 'page2BackgroundPath'),
            'page2.pdf'
        );

        if ($page1Background === null) {
            throw new \RuntimeException(__('No page 1 PDF background has been uploaded.'));
        }

        if ($page2Background === null) {
            $page2Background = $page1Background;
        }

        $student = $this->userGateway->getByID($gibbonPersonID, [
            'gibbonPersonID', 'preferredName', 'surname', 'firstName', 'username',
            'address1', 'address1District', 'address1Country', 'dob',
        ]);

        if (empty($student)) {
            throw new \RuntimeException(__('The specified student cannot be found.'));
        }

        $student = array_merge($student, $this->getFamilyAddress($gibbonPersonID));

        $programHistory = $this->programGateway->getAllProgramsByPerson($gibbonPersonID);
        $selectedProgram = resolveTranscriptsProgram($programHistory, $gibbonStudentProgramHistoryID);
        $transcriptData = $this->transcriptService->generateStudentTranscript($gibbonPersonID, $selectedProgram);

        $studentContext = $this->buildStudentContext($student, $selectedProgram);
        $registrarSignature = $isOfficial
            ? $this->resolveUploadPath($this->settingGateway->getSettingByScope('Transcripts', 'registrarSignaturePath'))
            : null;

        $layoutOptions = [
            'isOfficial' => $isOfficial,
            'unofficialNotice' => $isOfficial ? '' : __('Unofficial Transcript Copy'),
        ];

        if ($isOfficial) {
            $layoutOptions['registrarName'] = $this->getRegistrarOfficialName();
            $layoutOptions['registrarSignaturePath'] = $registrarSignature ?? '';
        }

        $layout = $this->layoutService->buildOfficialLayout(
            $transcriptData,
            $studentContext,
            $selectedProgram,
            $layoutOptions
        );

        if ($isOfficial && !empty($registrarSignature)) {
            $layout['registrar']['signatureFile'] = $registrarSignature;
        }

        $contentPdfPath = $this->renderContentPdf($layout);
        $mergedPdf = $this->mergeWithVectorBackgrounds($contentPdfPath, $page1Background, $page2Background);

        if (is_file($contentPdfPath)) {
            unlink($contentPdfPath);
        }

        return $mergedPdf;
    }

    public function buildFilename(int $gibbonPersonID, bool $isOfficial = true, int $gibbonStudentProgramHistoryID = 0): string
    {
        $student = $this->userGateway->getByID($gibbonPersonID, ['preferredName', 'surname']);
        $name = Format::name('', $student['preferredName'] ?? '', $student['surname'] ?? '', 'Student', false);
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $prefix = $isOfficial ? 'Transcript' : 'Transcript_Unofficial';

        $program = null;
        if ($gibbonStudentProgramHistoryID > 0) {
            $program = $this->programGateway->getByID($gibbonStudentProgramHistoryID);
        }
        $programSlug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $program['programType'] ?? '');

        return $prefix.'_'.$safeName.($programSlug ? '_'.$programSlug : '').'_'.date('Y-m-d').'.pdf';
    }

    private function renderContentPdf(array $layout): string
    {
        $tempDir = $this->absolutePath.'/uploads/transcripts/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = array_merge($defaultConfig['fontDir'], [$this->modulePath.'/assets/fonts']);
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        $fontData['alegreya'] = [
            'R' => 'Alegreya-Regular.ttf',
            'B' => 'Alegreya-Bold.ttf',
            'I' => 'Alegreya-Italic.ttf',
            'BI' => 'Alegreya-BoldItalic.ttf',
        ];

        $mpdf = new Mpdf([
            'mode' => '',
            'format' => 'Letter',
            'orientation' => 'P',
            'margin_top' => 40,
            'margin_bottom' => 22,
            'margin_footer' => 6,
            'margin_left' => 18,
            'margin_right' => 24,
            'tempDir' => $tempDir,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => 'alegreya',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($this->renderTemplate($layout));

        $contentPath = tempnam($tempDir, 'transcript_content_').'.pdf';
        $mpdf->Output($contentPath, 'F');

        return $contentPath;
    }

    private function mergeWithVectorBackgrounds(string $contentPdfPath, string $page1Background, string $page2Background): string
    {
        $pdf = new Fpdi('P', 'mm', 'Letter');
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $contentPageCount = $pdf->setSourceFile($contentPdfPath);

        for ($pageNo = 1; $pageNo <= $contentPageCount; $pageNo++) {
            $backgroundFile = ($pageNo === 1) ? $page1Background : $page2Background;

            $pdf->setSourceFile($backgroundFile);
            $backgroundTemplate = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($backgroundTemplate);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($backgroundTemplate, 0, 0, $size['width'], $size['height'], true);

            $pdf->setSourceFile($contentPdfPath);
            $contentTemplate = $pdf->importPage($pageNo);
            $contentSize = $pdf->getTemplateSize($contentTemplate);
            $pdf->useTemplate($contentTemplate, 0, 0, $contentSize['width'], $contentSize['height'], true);
        }

        return $pdf->Output('', 'S');
    }

    private function renderTemplate(array $layout): string
    {
        ob_start();
        include $this->modulePath.'/templates/transcriptOfficial.php';

        return ob_get_clean();
    }

    private function buildStudentContext(array $student, ?array $program): array
    {
        $address = $this->formatAddress([
            $student['address1'] ?? '',
            $student['address1District'] ?? '',
            $student['address1Country'] ?? '',
        ]);

        if ($address === '') {
            $address = $this->formatAddress([
                $student['homeAddress'] ?? '',
                $student['homeAddressDistrict'] ?? '',
                $student['homeAddressCountry'] ?? '',
            ]);
        }

        $dob = $student['dob'] ?? '';
        if ($dob === '0000-00-00') {
            $dob = '';
        }

        $degreeProgram = $this->mapDegreeProgram($program['programType'] ?? '');
        $graduationBanner = '';

        if (($program['status'] ?? '') === 'Graduated') {
            $degreeName = strtoupper($degreeProgram ?: ($program['programType'] ?? 'DEGREE'));
            $graduationBanner = '—GRADUATED '.$degreeName.'—';
        }

        return [
            'displayName' => Format::name('', $student['preferredName'], $student['surname'], 'Student', false),
            'identifier' => $student['username'] ?? '',
            'address' => $address,
            'dateOfBirth' => $dob !== '' ? Format::date($dob) : '',
            'degreeProgram' => $degreeProgram,
            'dateAdmitted' => !empty($program['startDate']) ? Format::date($program['startDate']) : '',
            'dateGraduated' => !empty($program['graduationDate']) ? Format::date($program['graduationDate']) : '',
            'graduationBanner' => $graduationBanner,
        ];
    }

    private function getFamilyAddress(int $gibbonPersonID): array
    {
        $sql = "SELECT f.homeAddress, f.homeAddressDistrict, f.homeAddressCountry
                FROM gibbonFamilyChild AS fc
                INNER JOIN gibbonFamily AS f ON f.gibbonFamilyID = fc.gibbonFamilyID
                WHERE fc.gibbonPersonID = :gibbonPersonID
                ORDER BY f.gibbonFamilyID
                LIMIT 1";

        $row = $this->connection->select($sql, ['gibbonPersonID' => $gibbonPersonID])->fetch();

        return is_array($row) ? $row : [];
    }

    private function formatAddress(array $parts): string
    {
        $parts = array_map(function ($part) {
            $part = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ', ', (string)$part)));
            return trim($part, " ,");
        }, $parts);

        return implode(', ', array_filter($parts, function ($part) {
            return $part !== '';
        }));
    }

    private function mapDegreeProgram(string $programType): string
    {
        $map = [
            'BTh' => 'Bachelor of Theology',
            'MTS' => 'Master of Theological Studies',
            'Certificate' => 'Certificate Program',
            'Iconography' => 'Iconography Program',
            'Iconology' => 'Iconology Program',
            'Gap-Year' => 'Gap Year Program',
            'Non-Degree' => 'Non-Degree Program',
        ];

        return $map[$programType] ?? $programType;
    }

    private function getRegistrarOfficialName(): string
    {
        $registrarPersonID = (int)$this->settingGateway->getSettingByScope('Transcripts', 'registrarGibbonPersonID');
        if ($registrarPersonID <= 0) {
            return '';
        }

        $registrar = $this->userGateway->getByID($registrarPersonID, ['officialName', 'title', 'preferredName', 'surname']);
        if (empty($registrar)) {
            return '';
        }

        $officialName = trim((string)($registrar['officialName'] ?? ''));
        if ($officialName !== '') {
            return $officialName;
        }

        return Format::name($registrar['title'] ?? '', $registrar['preferredName'] ?? '', $registrar['surname'] ?? '', 'Staff', false);
    }

    private function resolveBackgroundPath(?string $relativePath, string $defaultFilename): ?string
    {
        $uploadPath = $this->resolveUploadPath($relativePath);
        if ($uploadPath !== null) {
            return $uploadPath;
        }

        $defaultPath = $this->modulePath.'/assets/backgrounds/'.$defaultFilename;
        if (is_file($defaultPath)) {
            return $defaultPath;
        }

        return null;
    }

    private function resolveUploadPath(?string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        $fullPath = realpath($this->absolutePath.'/'.ltrim($relativePath, '/'));
        if ($fullPath === false || !is_file($fullPath)) {
            return null;
        }

        $uploadsRoot = realpath($this->absolutePath.'/uploads');
        if ($uploadsRoot !== false && strpos($fullPath, $uploadsRoot) !== 0) {
            return null;
        }

        return $fullPath;
    }
}
