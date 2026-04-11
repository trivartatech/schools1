<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostBookmark;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    // ── Social Buzz / Posts ──────────────────────────────────────────────────────

    public function posts(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $query = Post::where('school_id', $school->id)
            ->where('is_approved', true)
            ->with([
                'user:id,name,avatar,user_type',
                'media',
                'comments' => fn($q) => $q->with('user:id,name,avatar')->latest()->limit(3),
            ])
            ->withCount(['likes', 'allComments as comments_count', 'bookmarks'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        // Visibility scoping
        if ($user->isStudent() || $user->isParent()) {
            $studentId = $this->resolveStudentId($user, $request);
            $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
                ->where('academic_year_id', app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null)
                ->first() : null;

            $query->where(function ($q) use ($history) {
                $q->where('visibility', 'school');
                if ($history) {
                    $q->orWhere(function ($sub) use ($history) {
                        $sub->where('visibility', 'class')
                            ->where('class_id', $history->class_id);
                    });
                }
            });
        } elseif ($user->isTeacher()) {
            $query->whereIn('visibility', ['school', 'staff', 'class']);
        }
        // Admin sees everything

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $posts = $query->paginate(15);

        // Attach user-specific flags
        $items = collect($posts->items())->map(function ($post) use ($user) {
            $post->is_liked      = $post->isLikedBy($user);
            $post->is_bookmarked = $post->isBookmarkedBy($user);
            $post->is_own        = $post->user_id === $user->id;
            return $post;
        });

        return response()->json([
            'posts'        => $items,
            'total'        => $posts->total(),
            'page'         => $posts->currentPage(),
            'last_page'    => $posts->lastPage(),
        ]);
    }

    public function createPost(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $validated = $request->validate([
            'content'    => 'required|string|max:5000',
            'visibility' => 'sometimes|in:school,class,staff',
            'type'       => 'sometimes|in:post,poll,event,achievement',
            'class_id'   => 'nullable|exists:course_classes,id',
            'tags'       => 'nullable|array',
            'tags.*'     => 'string|max:50',
            'media'      => 'nullable|array|max:10',
            'media.*'    => 'file|mimes:jpg,jpeg,png,gif,mp4,mov,pdf|max:20480',
        ]);

        $post = Post::create([
            'school_id'   => $school->id,
            'user_id'     => $user->id,
            'content'     => $validated['content'],
            'visibility'  => $validated['visibility'] ?? 'school',
            'type'        => $validated['type'] ?? 'post',
            'class_id'    => $validated['class_id'] ?? null,
            'tags'        => $validated['tags'] ?? [],
            'is_approved' => true, // auto-approve for now; can add moderation later
        ]);

        // Handle media uploads
        if ($request->hasFile('media')) {
            $sortOrder = 0;
            foreach ($request->file('media') as $file) {
                if (!$file->isValid()) continue;
                $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs("posts/{$school->id}/{$post->id}", $safeName, 'public');

                $post->media()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'sort_order'    => $sortOrder++,
                ]);
            }
        }

        $post->load(['user:id,name,avatar,user_type', 'media']);
        $post->loadCount(['likes', 'allComments as comments_count']);

        return response()->json([
            'success' => true,
            'post'    => $post,
        ], 201);
    }

    public function toggleLike(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $existing = PostLike::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'type'    => $request->input('type', 'like'),
            ]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function toggleBookmark(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $existing = PostBookmark::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            PostBookmark::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
            $bookmarked = true;
        }

        return response()->json([
            'bookmarked'      => $bookmarked,
            'bookmarks_count' => $post->bookmarks()->count(),
        ]);
    }

    public function postComments(Request $request, int $postId): JsonResponse
    {
        $school = app('current_school');
        $post   = Post::where('school_id', $school->id)->findOrFail($postId);

        $comments = PostComment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,avatar,user_type',
                'replies' => fn($q) => $q->with('user:id,name,avatar,user_type')->orderBy('created_at'),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'comments' => $comments->items(),
            'total'    => $comments->total(),
            'page'     => $comments->currentPage(),
        ]);
    }

    public function addComment(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $validated = $request->validate([
            'comment'   => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:post_comments,id',
        ]);

        // If replying, verify parent comment belongs to same post
        if (!empty($validated['parent_id'])) {
            PostComment::where('id', $validated['parent_id'])
                ->where('post_id', $post->id)
                ->firstOrFail();
        }

        $comment = PostComment::create([
            'post_id'   => $post->id,
            'user_id'   => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'comment'   => $validated['comment'],
        ]);

        $comment->load('user:id,name,avatar,user_type');

        return response()->json([
            'success'        => true,
            'comment'        => $comment,
            'comments_count' => $post->allComments()->count(),
        ], 201);
    }

    public function deletePost(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        // Only the author or admin can delete
        if ($post->user_id !== $user->id && !in_array($user->user_type->value, ['admin', 'super_admin', 'school_admin'])) {
            return response()->json(['message' => 'You can only delete your own posts.'], 403);
        }

        $post->delete(); // soft delete

        return response()->json(['success' => true, 'message' => 'Post deleted.']);
    }
}
