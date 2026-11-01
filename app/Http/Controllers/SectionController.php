<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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

    /** Nouvel ordre d'affichage des sections, après un glisser-déposer de leur titre. */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('edit', $project);
        $ids = $project->sections()->pluck('id')->all();
        $data = $request->validate([
            'ids' => ['required', 'array', 'size:'.count($ids)],
            'ids.*' => ['integer', 'distinct', Rule::in($ids)],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                Section::whereKey($id)->update(['position' => $i + 1]);
            }
        });

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
