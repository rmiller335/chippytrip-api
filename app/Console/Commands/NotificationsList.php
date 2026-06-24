<?php

namespace App\Console\Commands;

use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;

// =============================================================================
class NotificationsList extends Command {
	protected $signature = 'notifications:list {start?} {end?}';
	protected $description = 'Show notifications sent by flight';

	// =========================================================================
    public function handle() {
		$start = $this->argument('start');
		$start = (null == $start) ? Flight::min('departure_date') : new Carbon($start);

		$end = $this->argument('end');
		$end = (null == $end) ? Flight::max('departure_date') : new Carbon($end);

		$flights = Flight::where('departure_date', '>=', $start)
			->where('departure_date', '<=', $end)
		;

		$this->output->setDecorated(true);

		$defaultStyle = [ 'style' => new TableCellStyle([
			'fg' => 'bright-green'
		])];

		$enabledStyle = [ 'style' => new TableCellStyle([
			'fg' => 'green'
		])];

		$numberStyle =  [ 'style' => new TableCellStyle([
			'fg' => 'bright-green',
			'align' => 'right'
		])];

		$hdrTextStyle = [ 'style' => new TableCellStyle([
			'fg' => 'green'
		])];

		$hdrNumStyle = [ 'style' => new TableCellStyle([
			'fg' => 'green',
			'align' => 'right'
		])];

		$hdr = [
			new TableCell('Id', $hdrTextStyle),
			new TableCell('Flight', $hdrTextStyle),
			new TableCell('Departure', $hdrTextStyle),
			new TableCell('Status', $hdrTextStyle),
			new TableCell('# Listeners', $hdrNumStyle),
			new TableCell('# Callbacks', $hdrNumStyle),
			new TableCell('# Notifications', $hdrNumStyle),
		];

		$flights->chunk(25, function($chunk) use($hdr, $defaultStyle, $enabledStyle, $numberStyle) {
			$data = [];

			foreach($chunk as $flight) {
				$row = [];

				if($flight->watch->notifications->count()) {
					$last = $data[array_key_last($data)];

					if(is_object($last) && TableSeparator::class != get_class($last)) {
						$data[] = new TableSeparator();
					}
				}

				$row = [
					new TableCell((string) $flight->id, $defaultStyle),
					new TableCell($flight->flight, $defaultStyle),
					new TableCell($flight->departure_date->format('Y-m-d'), $defaultStyle),
					new TableCell($flight->watch->enabled ? 'Enabled' : '', $enabledStyle),
					new TableCell($flight->watch->listeners->count(), $numberStyle),
					new TableCell($flight->watch->callbacks->count(), $numberStyle),
					new TableCell($flight->watch->notifications->count(), $numberStyle),
				];

				$data[] = $row;

				$notifications = [];

				if($flight->watch->notifications->count()) {
					foreach($flight->watch->notifications->sortBy('created_at') as $not) {
						$notifications[] = $not->data['event'];
					}

					$data[] = [
						new TableCell('>>>', $defaultStyle),
						new TableCell(implode(' ', $notifications), [
							'colspan' => 6,
							'style' => $defaultStyle['style'],
						]),
					];

					$data[] = new TableSeparator();
				}
			}

			$table = new Table($this->output);
			$table->setHeaders($hdr);
			$table->setRows($data);
			$table->render();
		});
    }
}
