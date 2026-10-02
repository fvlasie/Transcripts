<?php

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_type_delete.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $name = trim((string) ($_GET['name'] ?? ''));
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $backQuery = 'program_manage.php';
    if ($gibbonPersonID !== '') {
        $backQuery .= '&gibbonPersonID='.$gibbonPersonID;
    }

    $page->breadcrumbs
        ->add(__('Program Management'), $backQuery)
        ->add(__('Delete'));

    if ($name === '' || !$container->get(StudentProgramGateway::class)->programTypeExists($name)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module').'/program_type_deleteProcess.php');
        echo $form->getOutput();
    }
}
