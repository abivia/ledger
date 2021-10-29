<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param JournalEntry $journalEntry
     * @return Response
     */
    public function show(JournalEntry $journalEntry): Response
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param JournalEntry $journalEntry
     * @return Response
     */
    public function update(Request $request, JournalEntry $journalEntry): Response
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param JournalEntry $journalEntry
     * @return Response
     */
    public function destroy(JournalEntry $journalEntry): Response
    {
        //
    }
}
