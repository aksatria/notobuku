@extends('layouts.notobuku')

@section('title', 'Pustakawan Digital - NOTOBUKU')

@section('content')
<style>
  @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');

  :root {
    --pd-bg: #eef2f7;
    --pd-surface: #ffffff;
    --pd-ink: #0f172a;
    --pd-muted: #64748b;
    --pd-border: #e2e8f0;
    --pd-primary: #1f6feb;
    --pd-primary-2: #0ea5a4;
    --pd-success: #16a34a;
    --pd-shadow: 0 14px 40px rgba(15, 23, 42, 0.10);
    --pd-radius-xl: 24px;
    --pd-radius-lg: 18px;
    --pd-radius: 14px;
    --pd-radius-sm: 10px;
    --pd-font: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    --pd-font-display: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  }

  .pd-app {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }

  .pd-panel {
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: var(--pd-radius-xl);
    box-shadow: var(--pd-shadow);
    overflow: hidden;
  }

  .pd-nav {
    display: grid;
    gap: 16px;
    padding: 18px;
  }

  .pd-brand {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .pd-brand-badge {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--pd-primary), var(--pd-primary-2));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-family: var(--pd-font-display);
  }

  .pd-brand h1 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--pd-ink);
    font-family: var(--pd-font-display);
  }

  .pd-brand p {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--pd-muted);
  }

  .pd-status-pill {
    justify-self: start;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 999px;
    background: #dcfce7;
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.35);
  }

  .pd-status-pill.mock {
    background: #fef3c7;
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.4);
  }

  .pd-nav-actions {
    display: grid;
    gap: 8px;
  }

  .pd-section-label {
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pd-muted);
  }

  .pd-search {
    width: 100%;
    border: 1px solid var(--pd-border);
    border-radius: 12px;
    padding: 8px 12px;
    font-size: 12px;
    background: #fff;
    color: var(--pd-ink);
  }

  .pd-search:focus {
    outline: none;
    border-color: var(--pd-primary);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.12);
  }

  .pd-conv-list {
    display: grid;
    gap: 8px;
    max-height: 420px;
    overflow-y: auto;
  }

  .pd-conv {
    padding: 10px 12px;
    border-radius: var(--pd-radius);
    border: 1px solid transparent;
    background: #f8fafc;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .pd-conv.active {
    background: #eaf2ff;
    border-color: #c7ddff;
  }

  .pd-conv-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--pd-ink);
  }

  .pd-conv-meta {
    margin-top: 4px;
    font-size: 11px;
    color: var(--pd-muted);
  }

  .pd-conv-preview {
    margin-top: 6px;
    font-size: 12px;
    color: #475569;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .pd-conv-time {
    margin-top: 6px;
    font-size: 11px;
    color: #94a3b8;
  }

  .pd-conv-actions {
    display: flex;
    gap: 6px;
  }

  .pd-conv-delete {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: 1px solid var(--pd-border);
    background: #fff;
    color: #ef4444;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }

  .pd-main {
    display: grid;
    gap: 16px;
  }

  .pd-main-header {
    padding: 16px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .pd-main-title {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .pd-avatar-lg {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: linear-gradient(135deg, #e0ecff, #d2f4f2);
    color: #1f6feb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
  }

  .pd-main-title h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--pd-ink);
  }

  .pd-main-title p {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--pd-muted);
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .pd-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--pd-success);
  }

  .pd-main-actions {
    display: flex;
    gap: 8px;
  }

  .pd-filters {
    padding: 10px 18px;
    border: 1px solid var(--pd-border);
    border-radius: var(--pd-radius-lg);
    background: #fbfdff;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }

  .pd-select,
  .pd-toggle {
    border: 1px solid var(--pd-border);
    border-radius: 10px;
    padding: 6px 10px;
    font-size: 12px;
    background: #fff;
  }

  .pd-chat {
    display: flex;
    flex-direction: column;
    min-height: 68vh;
  }

  .pd-stream {
    flex: 1;
    padding: 20px 18px;
    overflow-y: auto;
    display: grid;
    gap: 12px;
    background:
      radial-gradient(circle at 10px 10px, rgba(31, 111, 235, 0.04) 0, rgba(31, 111, 235, 0.04) 1px, transparent 1px) 0 0 / 24px 24px,
      radial-gradient(circle at 10px 10px, rgba(14, 165, 164, 0.05) 0, rgba(14, 165, 164, 0.05) 1px, transparent 1px) 12px 12px / 28px 28px,
      #f6f9fd;
  }

  .pd-empty {
    text-align: center;
    padding: 18px;
    border: 1px dashed var(--pd-border);
    border-radius: var(--pd-radius);
    color: var(--pd-muted);
    background: #fff;
  }

  .pd-msg {
    display: flex;
    gap: 8px;
    max-width: 88%;
    align-items: flex-start;
  }

  .pd-msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
  }

  .pd-msg.ai {
    align-self: flex-start;
  }

  .pd-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--pd-primary), var(--pd-primary-2));
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
    flex: 0 0 auto;
    margin-top: 4px;
    align-self: flex-start;
  }

  .pd-avatar-sm.pd-avatar-ai {
    background: linear-gradient(135deg, #0f766e, #22c55e);
    color: #f0fdf4;
    font-size: 13px;
  }

  .pd-avatar-sm i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1em;
    height: 1em;
  }

  .pd-bubble {
    background: #fff;
    border: 1px solid var(--pd-border);
    box-shadow: var(--pd-shadow);
    padding: 12px 14px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--pd-ink);
    position: relative;
  }

  .pd-msg.user .pd-bubble {
    background: #dbeafe;
    border-color: rgba(30, 64, 175, 0.2);
  }

  .pd-time {
    font-size: 11px;
    color: var(--pd-muted);
    margin-top: 6px;
    text-align: right;
  }

  .pd-body {
    font-size: 14px;
    line-height: 1.65;
    color: var(--pd-ink);
  }

  .pd-body p {
    margin: 0 0 12px;
  }

  .pd-body h3,
  .pd-body h4 {
    margin: 12px 0 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--pd-ink);
  }

  .pd-body ul,
  .pd-body ol {
    margin: 6px 0 12px 18px;
    padding: 0;
    list-style-position: outside;
  }

  .pd-body ul {
    list-style-type: disc;
    padding-left: 18px;
  }

  .pd-body ol {
    list-style-type: decimal;
    padding-left: 18px;
  }

  .pd-body li {
    margin: 6px 0;
  }

  .pd-body strong {
    font-weight: 700;
    color: #0f172a;
  }

  .pd-body .pd-highlight {
    background: rgba(31, 111, 235, 0.12);
    color: #1f4fd1;
    padding: 1px 6px;
    border-radius: 999px;
    font-weight: 600;
  }

  .pd-body .pd-inline-chip {
    background: rgba(14, 165, 164, 0.12);
    color: #0f766e;
    padding: 1px 6px;
    border-radius: 999px;
    font-weight: 600;
  }

  .pd-body .pd-spacer {
    height: 8px;
  }

  .pd-typing {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--pd-muted);
  }

  .pd-typing-dots {
    display: inline-flex;
    gap: 4px;
  }

  .pd-typing-dots span {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #94a3b8;
    animation: pdTyping 1.4s infinite;
  }

  .pd-typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
  }

  .pd-typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
  }

  @keyframes pdTyping {
    0%, 100% { transform: translateY(0); opacity: 0.6; }
    50% { transform: translateY(-3px); opacity: 1; }
  }

  .pd-body blockquote {
    margin: 8px 0 12px;
    padding: 8px 12px;
    border-left: 3px solid rgba(31, 111, 235, 0.35);
    background: #f7f9ff;
    border-radius: 8px;
    color: #1f2937;
  }

  .pd-bubble-actions {
    display: none;
  }

  .pd-results {
    margin-top: 10px;
    display: grid;
    gap: 10px;
  }

  .pd-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
  }

  .pd-book {
    border: 1px solid var(--pd-border);
    border-radius: var(--pd-radius-sm);
    padding: 10px;
    background: #fff;
    display: flex;
    gap: 10px;
  }

  .pd-cover {
    width: 56px;
    height: 76px;
    border-radius: 8px;
    background: #e8f0ff;
    color: #3153a8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    overflow: hidden;
    flex-shrink: 0;
  }

  .pd-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .pd-book h4 {
    font-size: 13px;
    font-weight: 700;
    color: var(--pd-ink);
    margin-bottom: 4px;
  }

  .pd-book p {
    font-size: 12px;
    color: var(--pd-muted);
    margin-bottom: 4px;
  }

  .pd-book-actions {
    margin-top: 6px;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }

  .pd-composer {
    padding: 12px 16px;
    border-top: 1px solid var(--pd-border);
    background: var(--pd-surface);
  }

  .pd-input-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
  }

  .pd-stop {
    display: none;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid var(--pd-border);
  }

  .pd-textarea {
    flex: 1;
    border-radius: var(--pd-radius-sm);
    border: 1px solid var(--pd-border);
    padding: 12px 14px;
    font-size: 14px;
    resize: none;
    min-height: 52px;
    max-height: 140px;
    background: #fff;
    font-family: var(--pd-font);
  }

  .pd-textarea:focus {
    outline: none;
    border-color: var(--pd-primary);
    box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.12);
  }

  .pd-quick {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
  }

  @media (max-width: 1024px) {
    .pd-app {
      grid-template-columns: 1fr;
    }
    .pd-nav {
      order: 2;
    }
    .pd-main {
      order: 1;
    }
  }

  @media (max-width: 640px) {
    .pd-msg {
      max-width: 100%;
      gap: 10px;
    }

    .pd-avatar-sm {
      width: 40px;
      height: 40px;
      border-radius: 14px;
      font-size: 16px;
    }

    .pd-bubble {
      padding: 16px 18px;
      font-size: 16px;
      line-height: 1.7;
      border-radius: 16px;
    }

    .pd-body p {
      margin-bottom: 12px;
    }

    .pd-grid {
      grid-template-columns: 1fr;
    }
    .pd-nav-actions,
    .pd-quick {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>

<div class="pd-app">
  <aside class="pd-panel pd-nav">
    <div class="pd-brand">
      <div class="pd-brand-badge">PD</div>
      <div>
        <h1>Pustakawan Digital</h1>
        <p>NOTOBUKU - Asisten literasi</p>
      </div>
    </div>
    <span class="pd-status-pill {{ $ai_mode == 'mock' ? 'mock' : '' }}">
      {{ $ai_mode == 'mock' ? 'Mode Demo' : 'AI Aktif' }}
    </span>

    <div class="pd-nav-actions">
      <button type="button" class="nb-btn" onclick="showConversationHistory()"><i class="fas fa-history"></i> Riwayat</button>
      <button type="button" class="nb-btn" onclick="startNewConversation()"><i class="fas fa-plus"></i> Percakapan Baru</button>
      <a href="{{ route('member.dashboard') }}" class="nb-btn"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="pd-section-label">Percakapan</div>
    <input class="pd-search" id="conversationSearch" type="text" placeholder="Cari riwayat..." />
    <div class="pd-conv-list" id="conversationList">
      @php($lastMessage = $messages->last())
      <div class="pd-conv active" data-conv-id="{{ $conversation->id }}" onclick="handleConversationClick(event, '{{ $conversation->id }}')">
        <div class="pd-conv-main">
          <div class="pd-conv-title">{{ $conversation->title }}</div>
          <div class="pd-conv-meta">Sedang aktif</div>
          @if($lastMessage)
            <div class="pd-conv-preview">{{ \Illuminate\Support\Str::limit($lastMessage->content, 90) }}</div>
            <div class="pd-conv-time">{{ $lastMessage->created_at->format('d/m/Y H:i') }}</div>
          @endif
        </div>
        <div class="pd-conv-actions">
          <button class="pd-conv-delete" title="Hapus percakapan" onclick="deleteConversationById('{{ $conversation->id }}')">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
    <button type="button" class="nb-btn" onclick="loadMoreConversations()">
      <i class="fas fa-sync-alt"></i> Muat Lebih Banyak
    </button>
  </aside>

  <main class="pd-main">
    <div class="pd-panel pd-main-header">
      <div class="pd-main-title">
        <div class="pd-avatar-lg"><i class="fas fa-robot"></i></div>
        <div>
          <h2 id="currentConversationTitle">{{ $conversation->title }}</h2>
          <p><span class="pd-dot"></span> Pustakawan Digital online</p>
        </div>
      </div>
      <div class="pd-main-actions">
        <button type="button" class="nb-iconbtn" title="Hapus Percakapan" onclick="deleteCurrentConversation()">
          <i class="fas fa-trash"></i>
        </button>
        <button type="button" class="nb-iconbtn" title="Ekspor Chat" onclick="exportConversation()">
          <i class="fas fa-download"></i>
        </button>
      </div>
    </div>

    @if(empty($ai_only))
      <div class="pd-filters">
        <label class="pd-toggle">
          <input type="checkbox" id="filterAvailableOnly">
          Tersedia saja
        </label>
        <select id="sortSelect" class="pd-select">
          <option value="relevant">Urutkan: Relevan</option>
          <option value="latest">Urutkan: Terbaru</option>
          <option value="popular">Urutkan: Populer</option>
        </select>
      </div>
    @endif

    <section class="pd-panel pd-chat">
      <div class="pd-stream" id="chatMessages">
        @if($messages->isEmpty())
          <div class="pd-empty">
            <strong>Mulai percakapan baru</strong>
            <div>Contoh: "cari buku pemrograman" atau "rekomendasi novel".</div>
          </div>
        @endif
      @foreach($messages as $message)
        <div class="pd-msg {{ $message->role }}">
          @if($message->role === 'ai')
            <div class="pd-avatar-sm pd-avatar-ai" title="Pustakawan Digital"><i class="fas fa-feather-alt"></i></div>
          @endif
          <div class="pd-bubble">
            <div class="pd-body" data-role="{{ $message->role }}" data-raw='@json($message->content)'></div>
            <div class="pd-time">{{ $message->created_at->format('H:i') }}</div>
          </div>
        </div>
      @endforeach
      </div>

      <div class="pd-composer">
        <div class="pd-input-row">
          <textarea class="pd-textarea" id="messageInput" placeholder="Tanya apa saja tentang buku..." rows="2"></textarea>
          <button class="nb-btn nb-btn-primary" id="sendButton" onclick="sendMessage()">Kirim</button>
          <button class="nb-btn pd-stop" id="stopButton" onclick="stopGenerating()">Stop</button>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
  const currentConversationId = '{{ $conversation->id }}';
  const currentUserId = {{ Auth::id() }};
  const csrfToken = '{{ csrf_token() }}';
  const apiBaseUrl = '{{ url("member/pustakawan-digital") }}';

  const messageInput = document.getElementById('messageInput');
  const sendButton = document.getElementById('sendButton');
  const stopButton = document.getElementById('stopButton');
  const chatMessages = document.getElementById('chatMessages');
  const conversationList = document.getElementById('conversationList');
  const currentConversationTitle = document.getElementById('currentConversationTitle');
  const filterAvailableOnly = document.getElementById('filterAvailableOnly');
  const sortSelect = document.getElementById('sortSelect');
  const conversationSearch = document.getElementById('conversationSearch');

  let lastQuery = '';
  let activeController = null;
  let typingEl = null;

  messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
  });

  messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!sendButton.disabled) {
        sendMessage();
      }
    }
  });

  function scrollToBottom(smooth = true) {
    if (smooth) {
      chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
    } else {
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
  }

  function showTyping() {
    if (typingEl) return;
    typingEl = document.createElement('div');
    typingEl.className = 'pd-msg ai';
    typingEl.innerHTML = `
      <div class="pd-avatar-sm pd-avatar-ai" title="Pustakawan Digital"><i class="fas fa-feather-alt"></i></div>
      <div class="pd-bubble">
        <div class="pd-typing">
          Sedang menulis
          <span class="pd-typing-dots">
            <span></span><span></span><span></span>
          </span>
        </div>
      </div>
    `;
    chatMessages.appendChild(typingEl);
    scrollToBottom();
  }

  function hideTyping() {
    if (typingEl) {
      typingEl.remove();
      typingEl = null;
    }
  }

  function stopGenerating() {
    if (activeController) {
      activeController.abort();
      activeController = null;
    }
    hideTyping();
    if (stopButton) {
      stopButton.style.display = 'none';
    }
    sendButton.disabled = false;
    messageInput.disabled = false;
    messageInput.focus();
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
  }

  function escapeAttr(text) {
    return String(text ?? '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function formatInline(text) {
    const escaped = escapeHtml(text);
    return escaped
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/__(.+?)__/g, '<strong>$1</strong>')
      .replace(/\b(penting|catatan|ringkas|kesimpulan|intinya|utama|kunci)\b/gi, '<span class="pd-highlight">$1</span>')
      .replace(/\b(contoh|misal|misalnya|tips|saran|langkah)\b/gi, '<span class="pd-inline-chip">$1</span>');
  }

  function normalizeTextBlocks(text) {
    let raw = String(text ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    raw = raw.replace(/(\d+[\.\)])\s+/g, '\n$1 ');
    raw = raw.replace(/\s+([•-])\s+/g, '\n$1 ');
    raw = raw.replace(/\n{3,}/g, '\n\n');
    return raw.trim();
  }

  function hasExplicitStructure(text) {
    return /(^#|^##|^###)/m.test(text) || /\b(ringkasan|kesimpulan|poin penting|poin-poin|rangkuman)\b/i.test(text);
  }

  function extractSentences(text) {
    const clean = String(text ?? '').replace(/\s+/g, ' ').trim();
    if (!clean) return [];
    const matches = clean.match(/[^.!?]+[.!?]+/g);
    if (matches) return matches.map(s => s.trim());
    return [clean];
  }

  function uniqueTrimmed(list, limit) {
    const seen = new Set();
    const out = [];
    for (const item of list) {
      const key = item.trim();
      if (!key || seen.has(key)) continue;
      seen.add(key);
      out.push(key);
      if (out.length >= limit) break;
    }
    return out;
  }

  function formatStructured(text) {
    const normalized = normalizeTextBlocks(text);
    if (!normalized) return `<p>${formatInline('')}</p>`;
    if (hasExplicitStructure(normalized)) {
      return formatList(normalized);
    }

    const paragraphs = normalized.split(/\n\s*\n/).map(p => p.trim()).filter(Boolean);
    const intro = paragraphs[0] || normalized;
    const rest = paragraphs.slice(1).join('\n\n');

    const lines = normalized.split('\n').map(l => l.trim()).filter(Boolean);
    const listLines = lines
      .filter(l => /^(\d+[\.\)]|[-•])\s+/.test(l))
      .map(l => l.replace(/^(\d+[\.\)]|[-•])\s+/, '').trim());

    const sentences = extractSentences(normalized);
    const points = uniqueTrimmed(listLines.length >= 2 ? listLines : sentences.slice(0, 6), 6);
    const summary = uniqueTrimmed(sentences.slice(-3), 3);

    let html = '';
    html += `<h4>${formatInline('Penjelasan')}</h4>`;
    html += `<p>${formatInline(intro)}</p>`;
    if (rest) {
      html += `<div class="pd-spacer"></div>`;
      html += formatList(rest);
    }
    if (points.length) {
      html += `<h4>${formatInline('Poin Penting')}</h4>`;
      html += `<ul>${points.map(p => `<li>${formatInline(p)}</li>`).join('')}</ul>`;
    }
    if (summary.length) {
      html += `<h4>${formatInline('Ringkasan')}</h4>`;
      html += `<ul>${summary.map(p => `<li>${formatInline(p)}</li>`).join('')}</ul>`;
    }
    return html;
  }

  function formatList(text) {
    const normalized = normalizeTextBlocks(text);
    const lines = normalized.split('\n').map(l => l.trim());
    let html = '';
    let buffer = [];
    let mode = null;

    const flush = () => {
      if (buffer.length === 0) return;
      if (mode === 'ol') {
        html += `<ol>${buffer.map(i => `<li>${formatInline(i)}</li>`).join('')}</ol>`;
      } else if (mode === 'ul') {
        html += `<ul>${buffer.map(i => `<li>${formatInline(i)}</li>`).join('')}</ul>`;
      }
      buffer = [];
      mode = null;
    };

    for (const line of lines) {
      if (line === '') {
        flush();
        html += `<div class="pd-spacer"></div>`;
        continue;
      }
      const h3Match = line.match(/^###\s+(.*)$/);
      const h2Match = line.match(/^##\s+(.*)$/);
      const h1Match = line.match(/^#\s+(.*)$/);
      const olMatch = line.match(/^(\d+)[\.\)]\s+(.*)$/);
      const ulMatch = line.match(/^[-•]\s+(.*)$/);
      const quoteMatch = line.match(/^>\s+(.*)$/);
      if (h3Match || h2Match || h1Match) {
        flush();
        const headingText = h3Match?.[1] || h2Match?.[1] || h1Match?.[1] || '';
        html += `<h4>${formatInline(headingText)}</h4>`;
      } else if (quoteMatch) {
        flush();
        html += `<blockquote>${formatInline(quoteMatch[1])}</blockquote>`;
      } else if (olMatch) {
        if (mode && mode !== 'ol') flush();
        mode = 'ol';
        buffer.push(olMatch[2]);
      } else if (ulMatch) {
        if (mode && mode !== 'ul') flush();
        mode = 'ul';
        buffer.push(ulMatch[1]);
      } else {
        flush();
        html += `<p>${formatInline(line)}</p>`;
      }
    }
    flush();
    return html || `<p>${formatInline(text)}</p>`;
  }

  function fallbackCover(title) {
    const t = String(title || '').trim();
    if (!t) return 'NB';
    const parts = t.split(' ').filter(Boolean);
    const initial = parts.slice(0, 2).map(p => p[0]).join('');
    return initial.toUpperCase();
  }

  function renderBookCard(book, source) {
    const title = escapeHtml(book.title || '');
    const author = escapeHtml(book.author || (Array.isArray(book.authors) ? book.authors.join(', ') : ''));
    const year = escapeHtml(book.year || book.published_date || '');
    const isbn = escapeAttr(book.isbn || '');
    const cover = book.cover_url
      ? `<img src="${escapeAttr(book.cover_url)}" alt="${title}">`
      : `<span>${escapeHtml(fallbackCover(book.title))}</span>`;
    const requestBtn = source ? `
      <button class="nb-btn nb-btn-primary"
        data-title="${escapeAttr(book.title || '')}"
        data-author="${escapeAttr(book.author || '')}"
        data-isbn="${isbn}"
        onclick="requestExternalBook(this)">
        Request Buku
      </button>` : '';
    const detailBtn = book.url ? `<a class="nb-btn" href="${escapeAttr(book.url)}">Detail</a>` : '';
    const availability = source === 'local'
      ? `<p>Tersedia: ${book.available_count || 0} eks.</p>`
      : '';

    return `
      <div class="pd-book">
        <div class="pd-cover">${cover}</div>
        <div>
          <h4>${title}</h4>
          ${author ? `<p>${author}</p>` : ''}
          ${year ? `<p>${year}</p>` : ''}
          ${availability}
          <div class="pd-book-actions">${requestBtn}${detailBtn}</div>
        </div>
      </div>
    `;
  }

  function buildAiMessage(response) {
    const rawMessage = response?.message || '';
    let html = `<div class="pd-body">${formatStructured(rawMessage)}</div>`;

    const books = response?.data?.books || [];
    if (books.length > 0) {
      const source = response?.data?.source || '';
      const total = response?.data?.total || books.length;
      const page = response?.data?.page || 1;
      const perPage = response?.data?.per_page || books.length;
      const query = response?.data?.query || '';
      const shown = Math.min(page * perPage, total);
      const hasMore = source === 'local' && total > (page * perPage);
      html += `
        <div class="pd-results">
          <div class="pd-section-label">Hasil (${shown}/${total})</div>
          <div class="pd-grid">
            ${books.map(book => renderBookCard(book, source)).join('')}
          </div>
          ${hasMore ? `
            <button class="nb-btn" onclick="loadMoreResults('${escapeAttr(query)}', ${page + 1})">Muat Lagi</button>
          ` : ''}
        </div>
      `;
    }

    return html;
  }

  function hydrateExistingMessages() {
    const nodes = document.querySelectorAll('.pd-body[data-raw]');
    nodes.forEach(node => {
      const raw = node.getAttribute('data-raw') || '""';
      const role = node.getAttribute('data-role') || 'ai';
      let content = '';
      try {
        content = JSON.parse(raw);
      } catch (e) {
        content = raw;
      }
      node.innerHTML = role === 'ai' ? formatStructured(content) : formatList(content);
    });
  }

  function addMessageToChat(content, role, responsePayload = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `pd-msg ${role}`;
    const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    const bodyHtml = responsePayload
      ? buildAiMessage(responsePayload)
      : `<div class="pd-body">${role === 'ai' ? formatStructured(content) : formatList(content)}</div>`;
    const avatar = role === 'ai'
      ? `<div class="pd-avatar-sm pd-avatar-ai" title="Pustakawan Digital"><i class="fas fa-feather-alt"></i></div>`
      : '';
    messageDiv.innerHTML = `
      ${avatar}
      <div class="pd-bubble">
        ${bodyHtml}
        <div class="pd-time">${time}</div>
      </div>
    `;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
  }

  async function requestAnswer(message, page = 1, showUser = true) {
    if (!message) return;
    lastQuery = message;

    if (showUser) {
      sendButton.disabled = true;
      messageInput.disabled = true;
      addMessageToChat(message, 'user');
      messageInput.value = '';
      messageInput.style.height = 'auto';
    }

    try {
      showTyping();
      if (stopButton) {
        stopButton.style.display = 'inline-flex';
      }
      activeController = new AbortController();
      const response = await fetch(`${apiBaseUrl}/ask`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        signal: activeController.signal,
        body: JSON.stringify({
          question: message,
          conversation_id: currentConversationId,
          page: page,
          available_only: !!filterAvailableOnly?.checked,
          sort: sortSelect?.value || 'relevant'
        })
      });

      let data = null;
      if (response.headers.get('content-type')?.includes('application/json')) {
        data = await response.json();
      } else {
        const raw = await response.text();
        if ((raw || '').toLowerCase().includes('usage limit')) {
          addMessageToChat('Kuota layanan AI sedang habis. Anda tetap bisa gunakan katalog, pinjaman, dan reservasi.', 'ai');
          return;
        }
        throw new Error('invalid_response');
      }

      if (response.ok && data && data.success) {
        addMessageToChat(data.response.message, 'ai', data.response);
        if (showUser && currentConversationTitle.textContent === 'Percakapan Baru') {
          currentConversationTitle.textContent = message.substring(0, 30) + (message.length > 30 ? '...' : '');
        }
      } else {
        const serverMsg = data?.response?.message || data?.message || '';
        const lowered = (serverMsg || '').toLowerCase();
        if (lowered.includes('usage limit') || lowered.includes('quota') || lowered.includes('rate limit')) {
          addMessageToChat('Kuota layanan AI sedang habis. Anda tetap bisa gunakan katalog, pinjaman, dan reservasi.', 'ai');
        } else {
          addMessageToChat(serverMsg || 'Maaf, sistem sedang sibuk. Coba lagi sebentar ya.', 'ai');
        }
      }
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }
      addMessageToChat('Maaf, koneksi bermasalah. Coba lagi sebentar ya.', 'ai');
    } finally {
      hideTyping();
      activeController = null;
      if (stopButton) {
        stopButton.style.display = 'none';
      }
      sendButton.disabled = false;
      messageInput.disabled = false;
      if (showUser) messageInput.focus();
    }
  }

  async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message) return;
    await requestAnswer(message, 1, true);
  }

  hydrateExistingMessages();

  async function loadMoreResults(query, nextPage) {
    if (!query || !nextPage) return;
    await requestAnswer(query, nextPage, false);
  }

  if (filterAvailableOnly) {
    filterAvailableOnly.addEventListener('change', () => {
      if (lastQuery) requestAnswer(lastQuery, 1, false);
    });
  }
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      if (lastQuery) requestAnswer(lastQuery, 1, false);
    });
  }

  function quickAction(text) {
    messageInput.value = text;
    messageInput.style.height = 'auto';
    messageInput.style.height = Math.min(messageInput.scrollHeight, 140) + 'px';
    messageInput.focus();
  }

  async function showConversationHistory() {
    try {
      const response = await fetch(`${apiBaseUrl}/conversation/history`, { credentials: 'same-origin' });
      if (!response.ok) {
        addMessageToChat('Maaf, gagal memuat riwayat. Coba refresh halaman.', 'ai');
        return;
      }
      const data = await response.json();
      let html = '';
      if (!data.conversations?.data || data.conversations.data.length === 0) {
        html = `
          <div class="pd-empty">
            <strong>Belum ada riwayat</strong>
            <div>Mulai chat baru untuk menyimpan percakapan.</div>
          </div>
        `;
      } else {
        data.conversations.data.forEach(conv => {
          const isActive = conv.id === currentConversationId;
          const preview = conv.last_message_preview || '';
          const time = conv.last_message_at ? new Date(conv.last_message_at).toLocaleString('id-ID') : '';
          html += `
            <div class="pd-conv ${isActive ? 'active' : ''}" data-conv-id="${conv.id}" onclick="handleConversationClick(event, '${conv.id}')">
              <div class="pd-conv-main">
                <div class="pd-conv-title">${conv.title}</div>
                <div class="pd-conv-meta">${new Date(conv.updated_at).toLocaleDateString('id-ID')}</div>
                ${preview ? `<div class="pd-conv-preview">${escapeHtml(preview)}</div>` : ''}
                ${time ? `<div class="pd-conv-time">${time}</div>` : ''}
              </div>
              <div class="pd-conv-actions">
                <button class="pd-conv-delete" title="Hapus percakapan" onclick="deleteConversationById('${conv.id}')">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          `;
        });
      }
      conversationList.innerHTML = html;
    } catch (error) {
      console.error('Error:', error);
    }
  }

  let conversationPage = 1;
  async function loadMoreConversations() {
    conversationPage += 1;
    try {
      const response = await fetch(`${apiBaseUrl}/conversation/history?page=${conversationPage}`, { credentials: 'same-origin' });
      if (!response.ok) {
        conversationPage -= 1;
        addMessageToChat('Maaf, gagal memuat riwayat tambahan. Coba lagi.', 'ai');
        return;
      }
      const data = await response.json();
      if (!data.conversations?.data || data.conversations.data.length === 0) {
        conversationPage -= 1;
        return;
      }
      const fragment = document.createDocumentFragment();
      data.conversations.data.forEach(conv => {
        const isActive = conv.id === currentConversationId;
        const preview = conv.last_message_preview || '';
        const time = conv.last_message_at ? new Date(conv.last_message_at).toLocaleString('id-ID') : '';
        const div = document.createElement('div');
        div.className = `pd-conv ${isActive ? 'active' : ''}`;
        div.setAttribute('data-conv-id', conv.id);
        div.onclick = (event) => handleConversationClick(event, conv.id);
        div.innerHTML = `
          <div class="pd-conv-main">
            <div class="pd-conv-title">${conv.title}</div>
            <div class="pd-conv-meta">${new Date(conv.updated_at).toLocaleDateString('id-ID')}</div>
            ${preview ? `<div class="pd-conv-preview">${escapeHtml(preview)}</div>` : ''}
            ${time ? `<div class="pd-conv-time">${time}</div>` : ''}
          </div>
          <div class="pd-conv-actions">
            <button class="pd-conv-delete" title="Hapus percakapan" onclick="deleteConversationById('${conv.id}')">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        `;
        fragment.appendChild(div);
      });
      conversationList.appendChild(fragment);
    } catch (error) {
      conversationPage -= 1;
      console.error('Error:', error);
    }
  }

  function loadConversation(conversationId) {
    window.location.href = `${apiBaseUrl}?conversation=${conversationId}`;
  }

  if (conversationSearch) {
    conversationSearch.addEventListener('input', () => {
      const query = conversationSearch.value.toLowerCase().trim();
      const items = conversationList.querySelectorAll('.pd-conv');
      items.forEach(item => {
        const title = item.querySelector('.pd-conv-title')?.textContent.toLowerCase() || '';
        const preview = item.querySelector('.pd-conv-preview')?.textContent.toLowerCase() || '';
        const visible = !query || title.includes(query) || preview.includes(query);
        item.style.display = visible ? '' : 'none';
      });
    });
  }

  async function startNewConversation() {
    try {
      const response = await fetch(`${apiBaseUrl}/conversation/new`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        const text = await response.text();
        console.error('New conversation failed', text);
        addMessageToChat('Maaf, gagal membuat percakapan baru.', 'ai');
        return;
      }
      const data = await response.json();
      if (data.success && data.conversation?.id) {
        window.location.href = `${apiBaseUrl}?conversation=${data.conversation.id}`;
      } else if (data.success) {
        window.location.reload();
      }
    } catch (error) {
      console.error('Error:', error);
    }
  }

  async function deleteCurrentConversation() {
    if (!confirm('Hapus percakapan ini? Tindakan ini tidak dapat dibatalkan.')) return;
    try {
      const response = await fetch(`${apiBaseUrl}/conversation/${currentConversationId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        addMessageToChat('Maaf, gagal menghapus percakapan.', 'ai');
        return;
      }
      const data = await response.json();
      if (data.success) startNewConversation();
    } catch (error) {
      console.error('Error:', error);
    }
  }

  async function deleteConversationById(conversationId) {
    if (!conversationId) return;
    if (!confirm('Hapus percakapan ini? Tindakan ini tidak dapat dibatalkan.')) return;
    try {
      const response = await fetch(`${apiBaseUrl}/conversation/${conversationId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        addMessageToChat('Maaf, gagal menghapus percakapan.', 'ai');
        return;
      }
      const data = await response.json();
      if (data.success) {
        if (conversationId == currentConversationId) {
          startNewConversation();
        } else {
          showConversationHistory();
        }
      }
    } catch (error) {
      console.error('Error:', error);
    }
  }

  function handleConversationClick(event, conversationId) {
    if (!conversationId) return;
    if (event?.target?.closest('.pd-conv-delete')) {
      return;
    }
    loadConversation(conversationId);
  }

  function exportConversation() {
    let exportText = `Percakapan Pustakawan Digital - NOTOBUKU\n`;
    exportText += `Tanggal: ${new Date().toLocaleDateString('id-ID')}\n`;
    exportText += `====================\n\n`;
    const messages = document.querySelectorAll('.pd-msg');
    messages.forEach(msg => {
      const role = msg.classList.contains('user') ? 'Anda' : 'Pustakawan';
      const content = msg.querySelector('.pd-body')?.textContent || '';
      const time = msg.querySelector('.pd-time')?.textContent || '';
      exportText += `[${time}] ${role}:\n${content}\n\n`;
    });
    const blob = new Blob([exportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `percakapan-notobuku-${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  async function requestExternalBook(button) {
    const title = button.dataset.title || '';
    const author = button.dataset.author || '';
    const isbn = button.dataset.isbn || '';
    if (!title) return;

    try {
      const response = await fetch(`${apiBaseUrl}/request-book`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          title,
          author,
          isbn,
          conversation_id: currentConversationId
        })
      });
      if (!response.ok) {
        addMessageToChat('Maaf, permintaan gagal. Coba lagi sebentar ya.', 'ai');
        return;
      }
      const data = await response.json();
      if (data.success) {
        addMessageToChat(`Permintaan buku "${title}" sudah dikirim.`, 'ai');
      } else {
        addMessageToChat('Maaf, permintaan gagal. Coba lagi sebentar ya.', 'ai');
      }
    } catch (error) {
      addMessageToChat('Maaf, permintaan gagal. Coba lagi sebentar ya.', 'ai');
    }
  }
</script>
@endsection
