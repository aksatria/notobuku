<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BookRequest;
use Illuminate\Http\Request;

class AiMonitorController extends Controller
{
    public function index()
    {
        $stats = [
            'total_conversations' => AiConversation::count(),
            'active_conversations' => AiConversation::where('is_active', true)->count(),
            'total_messages' => AiMessage::count(),
            'today_messages' => AiMessage::whereDate('created_at', today())->count(),
            'book_requests' => BookRequest::count(),
            'pending_requests' => BookRequest::where('status', 'pending')->count(),
        ];
        
        $recentConversations = AiConversation::with('user')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
        
        $popularTopics = AiMessage::where('role', 'user')
            ->select('content', DB::raw('COUNT(*) as count'))
            ->groupBy('content')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
        
        return view('admin.ai-monitor', compact('stats', 'recentConversations', 'popularTopics'));
    }
    
    public function conversationDetail($id)
    {
        $conversation = AiConversation::with(['user', 'messages'])
            ->findOrFail($id);
        
        return view('admin.ai-conversation-detail', compact('conversation'));
    }
}