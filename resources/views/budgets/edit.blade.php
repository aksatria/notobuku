@extends('layouts.notobuku')

@section('title', 'Edit Budget')

@section('content')
@php
  $budget = $budget ?? null;
  $branches = $branches ?? collect();
@endphp

<style>
  .saas-page{ max-width:900px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:14px; overflow-x:hidden; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:12.5px; color:var(--nb-muted); margin-top:4px; }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Edit Budget</h1>
        <div class="saas-sub">Perbarui alokasi budget.</div>
      </div>
      <a class="nb-btn" href="{{ route('acquisitions.budgets.index') }}">Kembali</a>
    </div>

    <form method="POST" action="{{ route('acquisitions.budgets.update', $budget->id) }}" style="margin-top:12px;">
      @csrf
      @method('PUT')
      <div class="field" style="margin-bottom:10px;">
        <label>Tahun</label>
        <input class="nb-field" name="year" value="{{ $budget->year }}">
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label>Cabang (opsional)</label>
        <select class="nb-field" name="branch_id">
          <option value="">- semua cabang -</option>
          @foreach($branches as $br)
            <option value="{{ $br->id }}" {{ (string)$budget->branch_id === (string)$br->id ? 'selected' : '' }}>
              {{ $br->name }} {{ $br->code ? '(' . $br->code . ')' : '' }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label>Amount</label>
        <input class="nb-field" type="number" step="0.01" name="amount" value="{{ $budget->amount }}">
      </div>
      <button class="nb-btn nb-btn-primary" type="submit">Simpan</button>
    </form>
  </div>
</div>
@endsection
