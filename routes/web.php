<?php

use Illuminate\Support\Facades\Route;
use Uspdev\Workflow\Http\Controllers\WorkflowBackupController;
use Uspdev\Workflow\Http\Controllers\WorkflowController;

Route::group(['prefix' => config('uspdev-workflow.prefix'), 'middleware' => ['web']], function () {

    Route::get('/', [WorkflowController::class, 'home'])->name('workflows.index');
    Route::get('/createdefinition', [WorkflowController::class, 'createDefinition'])->name('workflows.create-definition');
    Route::post('/createdefinition', [WorkflowController::class, 'storeDefinition'])->name('workflows.store-definition');
    Route::get('/listdefinitions', [WorkflowController::class, 'listDefinitions'])->name('workflows.list-definitions');
    Route::get('/definition/{definition}', [WorkflowController::class, 'showDefinition'])->name('workflows.showDefinition');
    Route::delete('/definition/{definition}', [WorkflowController::class, 'destroyDefinition'])->name('workflows.destroyDefinition');
    Route::get('/editdefinition/{definition}', [WorkflowController::class, 'editDefinition'])->name('workflows.editDefinition');
    Route::post('/updatedefinition/', [WorkflowController::class, 'updateDefinition'])->name('workflows.updateDefinition');
    Route::get('/exportdefinition/{definitionName}',[WorkflowController::class,'exportDefinition'])->name('workflows.exportDefinition');

    Route::get('/viewcreateobject', [WorkflowController::class, 'viewCreateObject'])->name('workflows.viewCreateObject');
    Route::get('/createobject/{definitionName}', [WorkflowController::class, 'createObject'])->name('workflows.createObject');
    Route::post('/createobject/{definitionName}', [WorkflowController::class, 'submitForm']);
    Route::get('/object/{id}', [WorkflowController::class, 'showObject'])->name('workflows.showObject');
    Route::get('/object/{id}/form/{transition}', [WorkflowController::class, 'showForm'])->name('workflows.showForm');
    Route::post('/object/{id}', [WorkflowController::class, 'submitForm'])->name('workflows.showObject');
    Route::get('/showuserobjects', [WorkflowController::class, 'showUserObjects'])->name('workflows.show-user-objects');
    Route::post('/apply-transition/{id}', [WorkflowController::class, 'applyTransition'])->name('workflows.applyTransition');
    Route::delete('/delete-object/{object}', [WorkflowController::class, 'deleteObject'])->name('workflows.delete-object');

    Route::put('/listdefinitions/setuser', [WorkflowController::class, 'setUser'])->name('workflows.setuser');
    Route::get('/atendimentos', [WorkflowController::class, 'atendimentos'])->name('workflows.atendimentos');

    Route::get('/backups', [WorkflowBackupController::class, 'backups_index'])->name('workflows.backups-idx');
    Route::get('/backups/gen-backups', [WorkflowBackupController::class, 'bckp_gen_all'])->name('workflows.gen-all-backups');
    Route::get('/backups/{workflowDefinition}/gen-backup', [WorkflowBackupController::class, 'def_bckp_gen'])->name('workflows.gen-backup');
    Route::get('/backups/{workflowDefinition}/list',[WorkflowBackupController::class, 'def_bckp_list'])->name('workflows.def-backup-list');
    Route::get('/backups/{workflowDefinition}/remove/{created_time}', [WorkflowBackupController::class, 'remove_bckp'])->name('workflows.def-remove-bckp');
    Route::get('/backups/{workflowDefinition}/remove-all', [WorkflowBackupController::class, 'remove_def_bckps'])->name('workflows.def-rmv-bckps');
    Route::get('/backups/remove-all', [WorkflowBackupController::class, 'remove_all_bckps'])->name('workflows.rmv-bckps');
    Route::get('/backups/{workflowDefinition}/restore/{created_time}', [WorkflowBackupController::class,'restore_backup'])->name('workflows.restore-bckp');
});