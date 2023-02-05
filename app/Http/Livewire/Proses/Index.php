<?php

namespace App\Http\Livewire\Proses;

use App\Models\Alternatif;
use App\Models\Kriteria;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;

class Index extends Component
{

	public $data;

	public function render()
	{
		$alternatifs = $this->proses();
		return view('livewire.proses.index', compact('alternatifs'));
	}

	public function print()
	{
		// abaikan garis error di bawah 'Pdf' jika ada.
		$pdf = Pdf::loadView('laporan.cetak', ['data' => $this->proses()])->output();
		// return $pdf->download('Laporan.pdf');
		return response()->streamDownload(fn () => print($pdf), 'Laporan.pdf');
	}

	// proses metode WP
	public function proses()
	{
		$alternatifs = Alternatif::orderBy('kode')->get();
		$kriterias = Kriteria::orderBy('kode')->get();
		// penentuan nilai bobot
		$bobots = [];
		foreach ($kriterias as $kr) {
			$bobots[] = $kr->bobot / $kriterias->sum('bobot');
		}

		// penentuan matriks keputusan
		$matrix = [];
		foreach ($alternatifs as $ka => $alt) {
			foreach ($alt->kriteria as $kk => $krit) {
				$matrix[$ka][$kk] = $krit->pivot->nilai;
			}
		}

		// penentuan nilai vektor S
		$vectors = [];
		foreach ($matrix as $mat) {
			$vec = [];
			foreach ($mat as $km => $m) {
				$vec[] = pow($m, $bobots[$km]);
			}
			$vectors[] = array_product($vec);
		}

		// penentuan nilai bobot preferensi
		$prefs = [];
		$sigma_si = array_sum($vectors);
		foreach ($vectors as $vector) {
			$prefs[] = $vector / $sigma_si;
		}

		// masukkan hasil penilaian ke data alternatif
		foreach ($alternatifs as $key => $alternatif) {
			$alternatif->nilai = round($prefs[$key], 4);
		}

		return $alternatifs;
	}
}