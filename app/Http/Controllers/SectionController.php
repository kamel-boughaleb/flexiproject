<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SectionController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('edit', $project);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $project->sections()->create($data + ['position' => ($project->sections()->max('position') ?? 0) + 1]);

        return back();
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('edit', $section->project);
        $section->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back();
    }

    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('edit', $section->project);
        abort_if($section->project->sections()->count() <= 1, 422, 'Un projet doit garder au moins une section.');
        $section->delete();

        return back();
    }
}
