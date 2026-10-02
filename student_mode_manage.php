<?php

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/student_mode_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Student Mode'));

    $page->return->addReturns([
        'warning1' => __('Some modes were skipped because they were not in the list.'),
    ]);

    $gibbonSchoolYearID = (int) ($_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'));

    echo '<p>'.__('Mode of instruction belongs to the student for each term. A student can be remote one term and in person the next. A term with no row is in person. Room and board are not prorated inside a term.').'</p>';

    $filterForm = Form::create('studentModeFilter', $session->get('absoluteURL').'/index.php', 'get');
    $filterForm->setFactory(DatabaseFormFactory::create($pdo));
    $filterForm->setClass('noIntBorder w-full');
    $filterForm->addHiddenValue('q', '/modules/'.$session->get('module').'/student_mode_manage.php');
    $row = $filterForm->addRow();
        $row->addLabel('gibbonSchoolYearID', __('School Year'));
        $row->addSelectSchoolYear('gibbonSchoolYearID', 'All')->selected($gibbonSchoolYearID)->required();
    $row = $filterForm->addRow();
        $row->addSearchSubmit($session);
    echo $filterForm->getOutput();

    $terms = $pdo->select(
        'SELECT gibbonSchoolYearTermID, name, sequenceNumber
         FROM gibbonSchoolYearTerm
         WHERE gibbonSchoolYearID = :gibbonSchoolYearID
         ORDER BY sequenceNumber, firstDay',
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    )->fetchAll();

    if (empty($terms)) {
        echo Format::alert(__('This school year has no terms.'), 'warning');
        return;
    }

    $students = $pdo->select(
        "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonYearGroup.nameShort AS yearGroup
         FROM gibbonStudentEnrolment
         JOIN gibbonPerson ON gibbonPerson.gibbonPersonID = gibbonStudentEnrolment.gibbonPersonID AND gibbonPerson.status = 'Full'
         LEFT JOIN gibbonYearGroup ON gibbonYearGroup.gibbonYearGroupID = gibbonStudentEnrolment.gibbonYearGroupID
         WHERE gibbonStudentEnrolment.gibbonSchoolYearID = :gibbonSchoolYearID
         ORDER BY gibbonYearGroup.sequenceNumber, gibbonPerson.surname, gibbonPerson.preferredName",
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    )->fetchAll();

    if (isset($_GET['modeCopied'])) {
        $copied = (int) $_GET['modeCopied'];
        $source = htmlspecialchars((string) ($_GET['modeSource'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo Format::alert(sprintf(
            __('Copied mode for %1$s term rows from %2$s. Terms that already had a mode this year were left unchanged.'),
            $copied,
            $source
        ), $copied > 0 ? 'success' : 'warning');
    }

    $previousYear = $pdo->selectOne(
        'SELECT prev.gibbonSchoolYearID, prev.name
         FROM gibbonSchoolYear AS prev
         JOIN gibbonSchoolYear AS cur ON cur.gibbonSchoolYearID = :gibbonSchoolYearID
         WHERE prev.sequenceNumber < cur.sequenceNumber
         ORDER BY prev.sequenceNumber DESC
         LIMIT 1',
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    );

    if (!empty($previousYear['name'])) {
        $copyForm = Form::create('copyStudentMode', $session->get('absoluteURL').'/modules/'.$session->get('module').'/student_mode_manageProcess.php');
        $copyForm->addHiddenValue('address', $session->get('address'));
        $copyForm->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        $copyForm->addHiddenValue('intent', 'copyMode');
        $copyForm->addRow()->addContent(sprintf(
            __('Copy each term\'s mode from %1$s onto the term with the same sequence this year, where this year does not already have a row.'),
            htmlspecialchars($previousYear['name'], ENT_QUOTES, 'UTF-8')
        ));
        $copyForm->addRow()->addSubmit(sprintf(__('Copy from %1$s'), $previousYear['name']));
        echo $copyForm->getOutput();
    }

    if (empty($students)) {
        echo Format::alert(__('There are no students enrolled in this school year.'), 'message');
        return;
    }

    $modeRows = $pdo->select(
        'SELECT gibbonStudentInstructionMode.gibbonPersonID, gibbonStudentInstructionMode.gibbonSchoolYearTermID, gibbonStudentInstructionMode.modeOfInstruction
         FROM gibbonStudentInstructionMode
         JOIN gibbonSchoolYearTerm ON gibbonSchoolYearTerm.gibbonSchoolYearTermID = gibbonStudentInstructionMode.gibbonSchoolYearTermID
         WHERE gibbonSchoolYearTerm.gibbonSchoolYearID = :gibbonSchoolYearID',
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    )->fetchAll();
    $modes = [];
    foreach ($modeRows as $modeRow) {
        $modes[$modeRow['gibbonPersonID']][$modeRow['gibbonSchoolYearTermID']] = $modeRow['modeOfInstruction'];
    }

    $form = Form::create('studentMode', $session->get('absoluteURL').'/modules/'.$session->get('module').'/student_mode_manageProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $modeTableRows = [];
    $count = 0;
    foreach ($students as $student) {
        $personID = (int) $student['gibbonPersonID'];
        $termModes = [];
        foreach ($terms as $term) {
            $termID = (int) $term['gibbonSchoolYearTermID'];
            $termModes[$termID] = $modes[$personID][$termID] ?? 'In-person';
        }
        $modeTableRows[] = [
            'index' => $count,
            'gibbonPersonID' => $personID,
            'preferredName' => $student['preferredName'],
            'surname' => $student['surname'],
            'yearGroup' => (string) ($student['yearGroup'] ?? ''),
            'termModes' => $termModes,
        ];
        $count++;
    }

    $factory = $form->getFactory();
    $table = $form->addRow()->addDataTable('studentMode')->withData(new DataSet($modeTableRows));
    $table->setTitle(__('Student Mode'));
    $table->addColumn('student', __('Student'))
        ->format(function ($row) {
            $name = Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
            if ($row['yearGroup'] !== '') {
                $name .= ' <span class="text-xs text-gray-600">'.htmlspecialchars($row['yearGroup']).'</span>';
            }

            return $name.'<input type="hidden" name="gibbonPersonID'.$row['index'].'" value="'.$row['gibbonPersonID'].'">';
        });
    foreach ($terms as $term) {
        $termID = (int) $term['gibbonSchoolYearTermID'];
        $table->addColumn('term'.$termID, $term['name'])
            ->format(function ($row) use ($factory, $termID) {
                return $factory->createSelect('mode'.$row['index'].'_'.$termID)
                    ->fromArray(getTranscriptsInstructionModes())
                    ->required()
                    ->selected($row['termModes'][$termID] ?? 'In-person')
                    ->getOutput();
            });
    }

    $form->addHiddenValue('count', $count);
    $form->addHiddenValue('termIDs', implode(',', array_map(function ($term) {
        return (int) $term['gibbonSchoolYearTermID'];
    }, $terms)));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
