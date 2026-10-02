<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $programGateway = $container->get(StudentProgramGateway::class);
    $gibbonStudentProgramHistoryID = (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0);
    $program = $gibbonStudentProgramHistoryID > 0
        ? $programGateway->getByID($gibbonStudentProgramHistoryID)
        : null;

    $filterGibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $backQuery = 'program_manage.php';
    if (!empty($filterGibbonPersonID)) {
        $backQuery .= '&gibbonPersonID='.$filterGibbonPersonID;
    }

    if (empty($program)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $switching = ($_GET['switch'] ?? '') === '1';
        $page->breadcrumbs
            ->add(__('Program Dates Management'), $backQuery)
            ->add($switching ? __('Switch') : __('Edit'));

        $student = $container->get(UserGateway::class)->getByID($program['gibbonPersonID'], ['preferredName', 'surname']);
        $studentName = Format::name('', $student['preferredName'] ?? '', $student['surname'] ?? '', 'Student', false);

        if ($switching && ($program['status'] ?? '') !== 'Active') {
            $page->addError(__('Only an active program can be switched.'));
            return;
        }

        if ($switching) {
            $currentStart = $pdo->selectOne("SELECT firstDay FROM gibbonSchoolYear WHERE status = 'Current' ORDER BY sequenceNumber DESC LIMIT 1");
            $switchDefault = '';
            $switchSource = __('Must be after this program started. This program ends the day before the date, and the new program starts on it.');
            if (!empty($currentStart) && $currentStart !== '0000-00-00' && $currentStart > ($program['startDate'] ?? '')) {
                $switchDefault = Format::date($currentStart);
                $switchSource = __('The first day of the current school year. This program ends the day before that date, and the new program starts on it.');
            }

            $form = Form::create('programSwitch', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manage_editProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('intent', 'switchProgram');
            $form->addHiddenValue('gibbonStudentProgramHistoryID', $gibbonStudentProgramHistoryID);
            $form->addHiddenValue('filterGibbonPersonID', $filterGibbonPersonID);

            $row = $form->addRow();
                $row->addLabel('studentDisplay', __('Student'));
                $row->addTextField('studentDisplay')->readonly()->setValue($studentName);

            $row = $form->addRow();
                $row->addLabel('currentProgram', __('Current Program'));
                $row->addTextField('currentProgram')->readonly()->setValue($program['programType'].' ('.__('started').' '.Format::date($program['startDate']).')');

            $row = $form->addRow();
                $row->addLabel('programType', __('New Program'));
                $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes($pdo))->required()->placeholder();

            $row = $form->addRow();
                $row->addLabel('switchDate', __('Switch Date'))->description($switchSource);
                $switchDate = $row->addDate('switchDate')->required();
                if ($switchDefault !== '') {
                    $switchDate->setValue($switchDefault);
                }

            $row = $form->addRow();
                $row->addLabel('notes', __('Notes'));
                $row->addTextArea('notes')->setRows(3);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit(__('Switch'));

            echo $form->getOutput();
            return;
        }

        $form = Form::create('programEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manage_editProcess.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonStudentProgramHistoryID', $gibbonStudentProgramHistoryID);
        $form->addHiddenValue('gibbonPersonID', $program['gibbonPersonID']);
        $form->addHiddenValue('filterGibbonPersonID', $filterGibbonPersonID);

        $row = $form->addRow();
            $row->addLabel('studentDisplay', __('Student'));
            $row->addTextField('studentDisplay')->readonly()->setValue($studentName);

        $row = $form->addRow();
            $row->addLabel('programType', __('Program Type'));
            $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes($pdo))->required()->selected($program['programType']);

        $row = $form->addRow();
            $row->addLabel('startDate', __('Start Date'))->description(__('Required'));
            $row->addDate('startDate')->required()->setValue(Format::date($program['startDate']));

        $row = $form->addRow();
            $row->addLabel('switchDate', __('Switch Date'));
            $row->addDate('switchDate')->setValue(!empty($program['switchDate']) ? Format::date($program['switchDate']) : '');

        $row = $form->addRow();
            $row->addLabel('graduationDate', __('Graduation Date'));
            $row->addDate('graduationDate')->setValue(!empty($program['graduationDate']) ? Format::date($program['graduationDate']) : '');

        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')->fromArray(getTranscriptsProgramStatuses())->required()->selected($program['status']);

        $row = $form->addRow();
            $row->addLabel('notes', __('Notes'));
            $row->addTextArea('notes')->setRows(3)->setValue($program['notes'] ?? '');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    }
}
