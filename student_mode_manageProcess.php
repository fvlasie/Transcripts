<?php

use Gibbon\Data\Validator;
use Gibbon\Http\Url;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonSchoolYearID = (int) ($_POST['gibbonSchoolYearID'] ?? 0);
$URL = Url::fromModuleRoute('Transcripts', 'student_mode_manage.php')->withQueryParam('gibbonSchoolYearID', $gibbonSchoolYearID);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/student_mode_manage.php') == false) {
    header('Location: '.$URL->withReturn('error0'));
    exit;
}

checkAndMigrateTranscriptsSchema($pdo);

if (($_POST['intent'] ?? '') === 'copyMode') {
    $previous = $pdo->selectOne(
        'SELECT prev.gibbonSchoolYearID, prev.name
         FROM gibbonSchoolYear AS prev
         JOIN gibbonSchoolYear AS cur ON cur.gibbonSchoolYearID = :gibbonSchoolYearID
         WHERE prev.sequenceNumber < cur.sequenceNumber
         ORDER BY prev.sequenceNumber DESC
         LIMIT 1',
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    );

    $copied = 0;
    if (!empty($previous['gibbonSchoolYearID'])) {
        $params = [
            'targetYearTerm' => $gibbonSchoolYearID,
            'targetYearEnrolment' => $gibbonSchoolYearID,
            'sourceYear' => (int) $previous['gibbonSchoolYearID'],
        ];
        $from = 'FROM gibbonStudentInstructionMode AS prevMode
            JOIN gibbonSchoolYearTerm AS prevTerm ON prevTerm.gibbonSchoolYearTermID = prevMode.gibbonSchoolYearTermID
            JOIN gibbonSchoolYearTerm AS targetTerm ON targetTerm.gibbonSchoolYearID = :targetYearTerm AND targetTerm.sequenceNumber = prevTerm.sequenceNumber
            JOIN gibbonStudentEnrolment ON gibbonStudentEnrolment.gibbonPersonID = prevMode.gibbonPersonID
                AND gibbonStudentEnrolment.gibbonSchoolYearID = :targetYearEnrolment
            JOIN gibbonPerson ON gibbonPerson.gibbonPersonID = prevMode.gibbonPersonID AND gibbonPerson.status = \'Full\'
            WHERE prevTerm.gibbonSchoolYearID = :sourceYear
            AND NOT EXISTS (
                SELECT 1 FROM gibbonStudentInstructionMode AS cur
                WHERE cur.gibbonPersonID = prevMode.gibbonPersonID
                AND cur.gibbonSchoolYearTermID = targetTerm.gibbonSchoolYearTermID
            )';
        $copied = (int) $pdo->selectOne('SELECT COUNT(*) '.$from, $params);
        if ($copied > 0) {
            $pdo->statement(
                'INSERT INTO gibbonStudentInstructionMode (gibbonPersonID, gibbonSchoolYearTermID, modeOfInstruction)
                SELECT prevMode.gibbonPersonID, targetTerm.gibbonSchoolYearTermID, prevMode.modeOfInstruction '.$from,
                $params
            );
        }
    }

    header('Location: '.$URL->withQueryParams([
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'modeCopied' => $copied,
        'modeSource' => $previous['name'] ?? '',
    ]));
    exit;
}

$termIDs = array_filter(array_map('intval', explode(',', (string) ($_POST['termIDs'] ?? ''))));
$validTerms = $pdo->select(
    'SELECT gibbonSchoolYearTermID FROM gibbonSchoolYearTerm WHERE gibbonSchoolYearID = :gibbonSchoolYearID',
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchAll();
$validTermIDs = array_flip(array_map('intval', array_column($validTerms, 'gibbonSchoolYearTermID')));
$modes = getTranscriptsInstructionModes();
$count = (int) ($_POST['count'] ?? 0);
$skipped = false;

for ($i = 0; $i < $count; $i++) {
    $personID = (int) ($_POST['gibbonPersonID'.$i] ?? 0);
    if ($personID <= 0) {
        continue;
    }

    $enrolled = $pdo->selectOne(
        "SELECT gibbonStudentEnrolmentID FROM gibbonStudentEnrolment
         JOIN gibbonPerson ON gibbonPerson.gibbonPersonID = gibbonStudentEnrolment.gibbonPersonID AND gibbonPerson.status = 'Full'
         WHERE gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonPersonID = :gibbonPersonID",
        ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $personID]
    );
    if (empty($enrolled)) {
        $skipped = true;
        continue;
    }

    foreach ($termIDs as $termID) {
        if (!isset($validTermIDs[$termID])) {
            continue;
        }
        $mode = (string) ($_POST['mode'.$i.'_'.$termID] ?? '');
        if (!isset($modes[$mode])) {
            $skipped = true;
            continue;
        }

        $pdo->statement(
            'INSERT INTO gibbonStudentInstructionMode (gibbonPersonID, gibbonSchoolYearTermID, modeOfInstruction)
             VALUES (:gibbonPersonID, :gibbonSchoolYearTermID, :modeOfInstruction)
             ON DUPLICATE KEY UPDATE modeOfInstruction = VALUES(modeOfInstruction)',
            [
                'gibbonPersonID' => $personID,
                'gibbonSchoolYearTermID' => $termID,
                'modeOfInstruction' => $mode,
            ]
        );
    }
}

header('Location: '.$URL->withReturn($skipped ? 'warning1' : 'success0'));
