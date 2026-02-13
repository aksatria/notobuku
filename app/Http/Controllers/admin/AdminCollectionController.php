<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Biblio;
use Illuminate\Http\Request;

class AdminCollectionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Biblio::query()
            ->with(['authors'])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%' . $q . '%')
                    ->orWhere('subtitle', 'like', '%' . $q . '%')
                    ->orWhere('responsibility_statement', 'like', '%' . $q . '%')
                    ->orWhere('isbn', 'like', '%' . $q . '%')
                    ->orWhere('publisher', 'like', '%' . $q . '%');
            });
        }

        $books = $query->paginate(20)->withQueryString();

        return view('admin.collections', [
            'books' => $books,
            'q' => $q,
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Biblio::query()
            ->with(['authors'])
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', '%' . $q . '%')
                    ->orWhere('subtitle', 'like', '%' . $q . '%')
                    ->orWhere('isbn', 'like', '%' . $q . '%')
                    ->orWhereHas('authors', function ($authorQuery) use ($q) {
                        $authorQuery->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        $books = $query->paginate(20)->withQueryString();

        $payload = $books->getCollection()->map(function (Biblio $book) {
            $authors = $book->authors->pluck('name')->filter()->take(2)->values();
            return [
                'id' => $book->id,
                'title' => $book->display_title,
                'subtitle' => $book->subtitle,
                'publisher' => $book->publisher,
                'isbn' => $book->isbn,
                'publish_year' => $book->publish_year,
                'authors' => $authors,
                'items_count' => $book->items_count ?? 0,
                'available_items_count' => $book->available_items_count ?? 0,
                'cover_url' => $book->cover_path ? asset('storage/' . $book->cover_path) : null,
                'show_url' => route('katalog.show', $book->id),
                'edit_url' => route('katalog.edit', $book->id),
                'delete_url' => route('katalog.destroy', $book->id),
            ];
        })->values();

        return response()->json([
            'data' => $payload,
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
                'from' => $books->firstItem(),
                'to' => $books->lastItem(),
            ],
        ]);
    }
}
