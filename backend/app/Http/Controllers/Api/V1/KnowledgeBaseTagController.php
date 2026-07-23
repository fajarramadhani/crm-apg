<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KnowledgeBaseTagResource;
use App\Models\KnowledgeBaseTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class KnowledgeBaseTagController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('knowledge_base.view');

        $query = KnowledgeBaseTag::query();
        if (! $request->user()->hasPermission('knowledge_base.manage_tags')) {
            $query->where('is_active', true);
        }

        $tags = $query->orderBy('name', 'asc')->get();

        return KnowledgeBaseTagResource::collection($tags);
    }

    public function store(Request $request)
    {
        Gate::authorize('knowledge_base.manage_tags');

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:knowledge_base_tags,name',
        ]);

        $tag = KnowledgeBaseTag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
        ]);

        return new KnowledgeBaseTagResource($tag);
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.manage_tags');

        $tag = KnowledgeBaseTag::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:50|unique:knowledge_base_tags,name,'.$tag->id,
            'is_active' => 'sometimes|required|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tag->update($validated);

        return new KnowledgeBaseTagResource($tag);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('knowledge_base.manage_tags');

        $tag = KnowledgeBaseTag::findOrFail($id);

        // Deactivate instead of hard delete
        $tag->update(['is_active' => false]);

        return response()->noContent();
    }
}
