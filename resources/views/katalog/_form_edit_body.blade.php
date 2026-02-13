{{-- resources/views/katalog/_form_edit_body.blade.php --}}
<div class="kc-layout">

        {{-- LEFT --}}
        <div>

          {{-- Identitas --}}
          <div class="kc-section">
            <div class="kc-section-head">
              <div class="h">Identitas Bibliografi</div>
              <p class="nb-muted-2 hint">Minimal: Judul + Pengarang.</p>
            </div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Judul</label>
                <textarea class="nb-field" name="title" rows="2" required style="resize:vertical;">{{ old('title', $biblio->title) }}</textarea>
                <div class="nb-muted-2 kc-help">Judul boleh panjang. Gunakan enter jika perlu.</div>
                @error('title') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Subjudul</label>
                <textarea class="nb-field" name="subtitle" rows="2" style="resize:vertical;">{{ old('subtitle', $biblio->subtitle) }}</textarea>
                @error('subtitle') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px;"></div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Pengarang <span class="nb-muted-2">*</span></label>
                <textarea class="nb-field" name="authors_text" rows="2" required style="resize:vertical;"
                          placeholder="Pisahkan dengan koma jika lebih dari satu.">{{ old('authors_text', $authorsText ?? '') }}</textarea>
                @error('authors_text') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Subjek/Tajuk</label>
                <textarea class="nb-field" name="subjects_text" rows="2" style="resize:vertical;"
                          placeholder="Pisahkan dengan koma / titik koma / enter.">{{ old('subjects_text', $subjectsText ?? '') }}</textarea>
                @error('subjects_text') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Publikasi --}}
          <div class="kc-section">
            <div class="kc-section-head">
              <div class="h">Publikasi</div>
              <p class="nb-muted-2 hint">Penerbit, tempat terbit, tahun.</p>
            </div>

            <div class="kc-grid-3">
              <div class="kc-field">
                <label>Penerbit</label>
                <input class="nb-field" name="publisher" value="{{ old('publisher', $biblio->publisher) }}">
                @error('publisher') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tempat Terbit</label>
                <input class="nb-field" name="place_of_publication" value="{{ old('place_of_publication', $biblio->place_of_publication) }}">
                @error('place_of_publication') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tahun</label>
                <input class="nb-field" type="number" name="publish_year" value="{{ old('publish_year', $biblio->publish_year) }}" min="0" max="2100">
                @error('publish_year') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px;"></div>

            <div class="kc-grid-3">
              <div class="kc-field">
                <label>Bahasa</label>
                <input class="nb-field" name="language" value="{{ old('language', $biblio->language) }}" placeholder="id">
                @error('language') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Edisi</label>
                <input class="nb-field" name="edition" value="{{ old('edition', $biblio->edition) }}">
                @error('edition') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Deskripsi Fisik</label>
                <input class="nb-field" name="physical_desc" value="{{ old('physical_desc', $biblio->physical_desc) }}">
                @error('physical_desc') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Klasifikasi --}}
          <div class="kc-section">
            <div class="kc-section-head">
              <div class="h">Klasifikasi</div>
              <p class="nb-muted-2 hint">Disarankan: DDC + Nomor Panggil.</p>
            </div>

            <div class="kc-grid-3">
              <div class="kc-field">
                <label>DDC</label>
                <input class="nb-field" name="ddc" value="{{ old('ddc', $biblio->ddc) }}">
                @error('ddc') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Nomor Panggil</label>
                <input class="nb-field" name="call_number" value="{{ old('call_number', $biblio->call_number) }}">
                @error('call_number') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tag (pisahkan dengan koma)</label>
                <input class="nb-field" name="tags_text" value="{{ old('tags_text', $tagsText ?? '') }}">
                @error('tags_text') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px;"></div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Catatan</label>
                <textarea class="nb-field" name="notes" rows="4" style="resize:vertical;">{{ old('notes', $biblio->notes) }}</textarea>
                @error('notes') <div class="kc-error nb-muted-2">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="kc-actions">
              <button class="nb-btn nb-btn-primary" type="submit">Simpan Perubahan</button>
              <a class="nb-btn" href="{{ route('katalog.show', $biblio->id) }}">Batal</a>
            </div>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="kc-side">
          <div class="kc-section">
            <div class="kc-section-head">
              <div class="h">Ringkasan</div>
              <p class="nb-muted-2 hint">Cek cepat sebelum simpan.</p>
            </div>

            <div class="nb-muted-2" style="line-height:1.6;">
              • Pastikan <b>Judul</b> dan <b>Pengarang</b> benar.<br>
              • Isi <b>DDC</b> + <b>No. Panggil</b> agar siap rak.<br>
              • Tajuk Subjek meningkatkan akurasi pencarian.
            </div>

            <div style="height:10px;"></div>

            <div class="nb-muted-2">
              <span class="nb-badge">ID: {{ $biblio->id }}</span>
              @if(!empty($biblio->isbn))
                <span class="nb-badge" style="margin-left:6px;">ISBN: {{ $biblio->isbn }}</span>
              @endif
            </div>
          </div>

          <div style="height:12px;"></div>

          <div class="kc-section">
            <div style="font-weight:900;">Aksi Lanjutan</div>
            <div class="nb-muted-2" style="margin-top:6px;">Kelola eksemplar lewat halaman detail.</div>
            <div style="height:10px;"></div>
            <a class="nb-btn nb-btn-primary" href="{{ route('katalog.show', $biblio->id) }}" style="width:100%; justify-content:center;">
              Buka Detail Bibliografi
            </a>
          </div>
        </div>

      </div>
