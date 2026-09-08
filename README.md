# Jurnal AI Chatbot 1.3.0

Plugin chatbot AI mandiri untuk OJS 3.3.x. Seluruh konfigurasi dilakukan dari
dashboard OJS; tidak perlu mengunggah file proxy dengan cPanel atau FTP.

## Unduh

[Unduh jurnalChatbot-1.3.0.tar.gz](./jurnalChatbot-1.3.0.tar.gz)

SHA-256:
`27a8d58737f657562aaaf048964639462af4db97fe5d86dd2703e3aa7ec6202d`

## Instalasi atau upgrade

1. Masuk sebagai administrator atau journal manager.
2. Buka **Settings > Website > Plugins > Upload A New Plugin**.
3. Unggah paket `jurnalChatbot-1.3.0.tar.gz` dan selesaikan instalasi/upgrade.
4. Aktifkan **Jurnal AI Chatbot** lalu buka **Settings**.
5. Pilih penyedia AI, masukkan API Key milik penyedia tersebut, periksa nama
   model, lalu simpan.

## Penyedia AI

- OpenAI / ChatGPT (Responses API)
- Google Gemini
- Anthropic Claude
- DeepSeek
- Groq
- OpenRouter
- Mistral AI

Kunci disimpan terpisah untuk setiap penyedia. Saat mengganti penyedia, isi
kunci penyedia yang baru. Mengosongkan kolom kunci saat menyimpan tidak akan
menghapus kunci yang sudah tersimpan. Nama model dapat disesuaikan apabila
penyedia mengubah atau menambah model.

## Penyesuaian tema

Pada mode **Otomatis mengikuti tema OJS**, chatbot mendeteksi warna utama dari
tema jurnal yang sedang aktif dan menyesuaikan header, tombol, pesan, tautan,
border, serta warna kontras teks. Journal Manager dapat memilih mode
**Warna manual** jika ingin menggunakan kode warna hex tertentu. Pengaturan
API juga menampilkan indikator ketika kunci untuk penyedia terpilih telah
tersimpan tanpa pernah menampilkan kembali isi kuncinya.

Kunci API hanya digunakan oleh endpoint internal di server OJS dan tidak
dikirim ke browser pengunjung. Plugin menerapkan pembatasan permintaan per IP,
membatasi ukuran riwayat percakapan, memvalidasi email penulis sebelum
menampilkan status naskah, serta menyaring keluaran chatbot sebelum dirender.

## Persyaratan

- OJS 3.3.x
- PHP dengan ekstensi cURL dan akses HTTPS keluar ke API penyedia yang dipilih
- API Key aktif dengan saldo/kuota yang memadai

## Catatan upgrade

Kolom Proxy URL tidak digunakan lagi. File `jurnal-proxy.php` lama dapat
dihapus setelah versi 1.3.0 berhasil diuji. OJS API Token juga tidak diperlukan
karena pemeriksaan status menggunakan layanan internal OJS. Konfigurasi
Anthropic dari versi 1.1.0 tetap dibaca sebagai konfigurasi lama sehingga
upgrade tidak langsung memutus chatbot.
