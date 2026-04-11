<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\PostMedia;
use App\Models\PostBookmark;
use App\Models\CourseClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Str;

class SocialFeedController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $user = auth()->user();

        $query = Post::with([
                'user:id,name,avatar,user_type',
                'media',
                'likes:id,post_id,user_id,type',
                'comments.user:id,name,avatar,user_type',
                'comments.replies.user:id,name,avatar,user_type',
                'bookmarks:id,post_id,user_id',
                'class:id,name',
            ])
            ->withCount(['likes', 'comments', 'bookmarks'])
            ->where('school_id', $schoolId)
            ->where('is_approved', true);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('content', 'like', "%{$search}%");
        }

        // Post type filter
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Bookmarks filter
        if ($request->filled('filter') && $request->filter === 'bookmarks') {
            $query->whereHas('bookmarks', fn($q) => $q->where('user_id', $user->id));
        }

        // Visibility filter
        if ($request->filled('filter') && in_array($request->filter, ['school', 'staff', 'class'])) {
            $query->where('visibility', $request->filter);
        }

        // Visibility-based access control
        $query->where(function ($q) use ($user) {
            $q->where('visibility', 'school');

            if (in_array($user->user_type, ['staff', 'teacher', 'admin', 'school_admin', 'super_admin', 'principal'])) {
                $q->orWhere('visibility', 'staff');
            }

            if ($user->isStudent() || $user->isParent()) {
                if ($user->class_id) {
                    $q->orWhere(fn($sub) => $sub->where('visibility', 'class')->where('class_id', $user->class_id));
                }
            } else {
                $q->orWhere('visibility', 'class');
            }
        });

        // Pinned first, then latest
        $posts = $query->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Classes for dropdown
        $classes = [];
        if ($user->isAdmin() || $user->isTeacher()) {
            $classes = CourseClass::where('school_id', $schoolId)->get(['id', 'name']);
        }

        // Per-post augmented data
        $posts->getCollection()->transform(function ($post) use ($user) {
            $post->is_bookmarked = $post->bookmarks->contains('user_id', $user->id);
            $post->user_reaction = $post->likes->where('user_id', $user->id)->first()?->type;
            $post->reactions_summary = $post->likes->groupBy('type')->map(fn($g, $t) => ['type' => $t, 'count' => $g->count()])->values();
            return $post;
        });

        // Trending hashtags: scan recent posts for #tags
        $trending = $this->getTrendingHashtags($schoolId);

        // Stories (featured event types)
        $stories = [
            ['name' => 'Annual Day', 'icon' => 'sparkles', 'color' => '#F59E0B', 'hasNew' => true],
            ['name' => 'Sports Week', 'icon' => 'football', 'color' => '#22C55E', 'hasNew' => true],
            ['name' => 'Art Gallery', 'icon' => 'color-palette', 'color' => '#EC4899', 'hasNew' => true],
            ['name' => 'Book Week', 'icon' => 'book', 'color' => '#6366F1', 'hasNew' => false],
            ['name' => 'Science Fair', 'icon' => 'flask', 'color' => '#3B82F6', 'hasNew' => false],
            ['name' => 'PTM', 'icon' => 'people', 'color' => '#0D9488', 'hasNew' => false],
        ];

        return Inertia::render('School/Social/Index', [
            'posts'    => $posts,
            'classes'  => $classes,
            'trending' => $trending,
            'stories'  => $stories,
            'filters'  => [
                'search' => $request->search,
                'type'   => $request->type ?? 'all',
                'filter' => $request->filter ?? 'all',
            ],
        ]);
    }

    private function getTrendingHashtags(int $schoolId): array
    {
        $recentPosts = Post::where('school_id', $schoolId)
            ->where('is_approved', true)
            ->where('created_at', '>=', now()->subDays(14))
            ->pluck('content', 'tags');

        $tagCounts = [];

        // Count from tags JSON field
        foreach ($recentPosts as $tags => $content) {
            if ($tags) {
                $tagsArr = json_decode($tags, true) ?: [];
                foreach ($tagsArr as $tag) {
                    $t = ltrim($tag, '#');
                    $tagCounts[$t] = ($tagCounts[$t] ?? 0) + 1;
                }
            }

            // Also extract #hashtags from content
            if ($content && preg_match_all('/#(\w+)/', $content, $matches)) {
                foreach ($matches[1] as $tag) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($tagCounts);

        return collect($tagCounts)->take(5)->map(fn($count, $tag) => [
            'tag'   => '#' . $tag,
            'posts' => $count,
        ])->values()->all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'content'    => 'nullable|string|max:5000',
            'visibility' => 'required|in:school,staff,class',
            'type'       => 'nullable|in:achievement,event,sports,gallery,update,birthday',
            'tags'       => 'nullable|array',
            'tags.*'     => 'string|max:50',
            'class_id'   => 'nullable|exists:course_classes,id',
            'media.*'    => 'nullable|file|mimetypes:image/jpeg,image/png,image/gif,video/mp4,video/quicktime,video/x-msvideo|max:20480',
        ]);

        if (!$request->content && !$request->hasFile('media')) {
            return back()->with('error', 'Post cannot be empty.');
        }

        // Auto-extract hashtags from content
        $tags = $request->tags ?? [];
        if ($request->content && preg_match_all('/#(\w+)/', $request->content, $matches)) {
            $tags = array_unique(array_merge($tags, array_map(fn($t) => '#' . $t, $matches[1])));
        }

        $post = Post::create([
            'school_id'   => app('current_school_id'),
            'user_id'     => auth()->id(),
            'content'     => $request->content,
            'visibility'  => $request->visibility,
            'type'        => $request->type ?? 'update',
            'tags'        => !empty($tags) ? array_values($tags) : null,
            'class_id'    => $request->class_id,
            'is_approved' => true,
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $index => $file) {
                $path = $file->store('posts/' . $post->id, 'public');
                $post->media()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'sort_order'    => $index,
                ]);
            }
        }

        return back()->with('success', 'Post published!');
    }

    public function update(Request $request, Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);
        if ($post->user_id !== auth()->id()) abort(403, 'You can only edit your own posts.');

        $request->validate(['content' => 'required|string|max:5000']);
        $post->update(['content' => $request->content]);

        return back()->with('success', 'Post updated.');
    }

    public function toggleReaction(Request $request, Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);

        $userId = auth()->id();
        $type = $request->input('type', 'like');
        $existing = PostLike::where('post_id', $post->id)->where('user_id', $userId)->first();

        if ($existing) {
            if ($existing->type === $type) {
                $existing->delete();
                return back();
            }
            $existing->update(['type' => $type]);
            return back();
        }

        PostLike::create(['post_id' => $post->id, 'user_id' => $userId, 'type' => $type]);
        return back();
    }

    public function addComment(Request $request, Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);

        $request->validate([
            'comment'   => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:post_comments,id',
        ]);

        $post->allComments()->create([
            'user_id'   => auth()->id(),
            'comment'   => $request->comment,
            'parent_id' => $request->parent_id,
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function togglePin(Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);
        if (!auth()->user()->isAdmin()) abort(403);

        $post->update([
            'is_pinned' => !$post->is_pinned,
            'pinned_at' => $post->is_pinned ? null : now(),
            'pinned_by' => $post->is_pinned ? null : auth()->id(),
        ]);

        return back()->with('success', 'Post ' . ($post->is_pinned ? 'pinned' : 'unpinned') . '.');
    }

    public function toggleBookmark(Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);

        $userId = auth()->id();
        $bm = PostBookmark::where('post_id', $post->id)->where('user_id', $userId)->first();
        $bm ? $bm->delete() : PostBookmark::create(['post_id' => $post->id, 'user_id' => $userId]);

        return back();
    }

    public function destroy(Post $post)
    {
        $schoolId = (int) app('current_school_id');
        if ($post->school_id !== $schoolId) abort(403);
        if ($post->user_id !== auth()->id() && !auth()->user()->isAdmin()) abort(403);

        $post->delete();
        return back()->with('success', 'Post removed.');
    }
}
