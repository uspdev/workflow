<?php

use Illuminate\Support\Facades\Route;
use Uspdev\Forms\Http\Controllers\FindController;
use Uspdev\Forms\Http\Controllers\DefinitionController;
use Uspdev\Forms\Http\Controllers\SubmissionController;

Route::group(['prefix' => config('uspdev-forms.prefix'), 'middleware' => ['web']], function () {

    Route::get('definitions', [DefinitionController::class, 'index'])->name('form-definitions.index');
    Route::get('definitions/create', [DefinitionController::class, 'create'])->name('form-definitions.create');
    Route::post('definitions', [DefinitionController::class, 'store'])->name('form-definitions.store');
    Route::get('definitions/{formDefinition}', [DefinitionController::class, 'show'])->name('form-definitions.show');
    Route::get('definitions/{formDefinition}/edit', [DefinitionController::class, 'edit'])->name('form-definitions.edit');
    Route::put('definitions/{formDefinition}', [DefinitionController::class, 'update'])->name('form-definitions.update');
    Route::delete('definitions/{formDefinition}', [DefinitionController::class, 'destroy'])->name('form-definitions.destroy');
    // Route::resource('definitions', FormDefinitionController::class);

    // resource do form submissions no contexto de um form definition
    Route::group(['prefix' => 'submissions/{formDefinition}'], function () {
        Route::get('/', [submissionController::class, 'index'])->name('form-submissions.index');
        Route::get('/create', [submissionController::class, 'create'])->name('form-submissions.create');
        Route::post('/', [submissionController::class, 'store'])->name('form-submissions.store');
        Route::get('/{formSubmission}/edit', [submissionController::class, 'edit'])->name('form-submissions.edit');
        Route::put('/{formSubmission}/edit', [submissionController::class, 'update'])->name('form-submissions.update');
        Route::delete('/{formSubmission}', [submissionController::class, 'destroy'])->name('form-submissions.destroy');
        Route::get('/{formSubmission}/download-file/{fieldName}', [submissionController::class, 'downloadFile'])->name('form-submissions.download-file');
    });

    Route::get('/find/disciplina', [FindController::class, 'disciplina'])->name('form.find.disciplina');
    Route::get('/find/pessoa', [FindController::class, 'pessoa'])->name('form.find.pessoa');
    Route::get('/find/patrimonio', [FindController::class, 'patrimonio'])->name('form.find.patrimonio');
    Route::get('/find/local', [FindController::class, 'local'])->name('form.find.local');
});
