<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $filterGibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $suggested = $container->get(StudentProgramGateway::class)->suggestProgramDates((int)$filterGibbonPersonID);
    $backQuery = 'program_manage.php';
    if (!empty($filterGibbonPersonID)) {
        $backQuery .= '&gibbonPersonID='.$filterGibbonPersonID;
    }

    $page->breadcrumbs
        ->add(__('Program Dates Management'), $backQuery)
        ->add(__('Add'));

    $form = Form::create('programAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('filterGibbonPersonID', $filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'))->description(__('Required'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])->required()->placeholder()->selected($filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('programType', __('Program Type'));
        $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes($pdo))->required();

    $row = $form->addRow();
        $row->addLabel('startDate', __('Start Date'))->description($suggested['startSource'] !== '' ? $suggested['startSource'] : __('Required'));
        $startDate = $row->addDate('startDate')->required();
        if (!empty($suggested['startDate'])) {
            $startDate->setValue(Format::date($suggested['startDate']));
        }

    $row = $form->addRow();
        $row->addLabel('switchDate', __('Switch Date'));
        $row->addDate('switchDate');

    $row = $form->addRow();
        $row->addLabel('graduationDate', __('Graduation Date'))->description($suggested['graduationSource']);
        $graduationDate = $row->addDate('graduationDate');
        if (!empty($suggested['graduationDate'])) {
            $graduationDate->setValue(Format::date($suggested['graduationDate']));
        }

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray(getTranscriptsProgramStatuses())->required()->selected('Active');

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    $reloadURL = $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/program_manage_add.php';
    echo '<script>
    var student = document.querySelector("form#programAdd select[name=gibbonPersonID]");
    if (student) {
        student.addEventListener("change", function () {
            if (!this.value) return;
            window.location = '.json_encode($reloadURL).' + "&gibbonPersonID=" + encodeURIComponent(this.value);
        });
    }
    </script>';
}
