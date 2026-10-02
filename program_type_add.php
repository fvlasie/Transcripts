<?php

use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_type_add.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->return->addReturns([
        'error3' => __('Enter a program name of up to 30 characters, without a comma.'),
        'error4' => __('That program is already in the list.'),
    ]);

    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $backQuery = 'program_manage.php';
    if ($gibbonPersonID !== '') {
        $backQuery .= '&gibbonPersonID='.$gibbonPersonID;
    }

    $page->breadcrumbs
        ->add(__('Program Management'), $backQuery)
        ->add(__('Add'));

    $form = Form::create('programTypeAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_type_addProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('name', __('Program'))->description(__('Up to 30 characters, without a comma. This name is shared with Tuition Billing.'));
        $row->addTextField('name')->maxLength(30)->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
