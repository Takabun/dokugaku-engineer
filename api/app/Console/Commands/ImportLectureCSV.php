<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class ImportLectureCSV extends Command
{
    /**
     * alphaID の文字数
     *
     * @var int
     */
    const PAD_UP = 5;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:lecture-csv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import csv data of course, part, lesson and lecture';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tables = ['course', 'part', 'lesson', 'lecture'];
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            $this->insert($table);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * CSVデータを保存する
     *
     * @param string $name
     * @return void
     */
    private function insert(string $name): void
    {
        DB::table("${name}s_olds")->truncate();
        $csv = $this->getCsv($name);

        if ($name == 'lecture') {
            $csv = $this->addSlugs($csv);
        }

        // TODO: データのバリデーションを行う
        DB::table("${name}s_olds")->insert($csv);
        Schema::rename("${name}s", "${name}s_tmp");
        Schema::rename("${name}s_olds", "${name}s");
        Schema::rename("${name}s_tmp", "${name}s_olds");
    }

    /**
     * コースのCSVデータを取得する
     *
     * @param string $name
     * @return array
     */
    private function getCsv(string $name): array
    {
        $csv_data = Storage::disk(env('FILE_DISK', 'local'))->get("lecture/${name}.csv");
        $csv_lines = explode(PHP_EOL, $csv_data);
        $csv = [];
        foreach ($csv_lines as $line) {
            $csv[] = str_getcsv($line);
        }

        $data = [];
        $header = array_shift($csv);
        foreach ($csv as $row) {
            if (empty($row[0])) {
                continue;
            }

            $row_data = [];
            foreach ($row as $k => $v) {
                if ($v === '') {
                    $v = null;
                }
                $row_data[$header[$k]] = $v;
                $row_data['created_at'] = Carbon::now()->toDateTimeString();
                $row_data['updated_at'] = Carbon::now()->toDateTimeString();
            }
            $data[] = $row_data;
        }
        return $data;
    }

    /**
     * CSVデータにslug, prev_lecture_slug, next_lecture_slugカラムを追加する
     *
     * @param array $csv
     * @return array
     */
    private function addSlugs(array $csv): array
    {
        # 削除されているかで列を分割
        $existing_lectures = array_filter($csv, function ($row) {
            return $row['deleted_at'] === null;
        });
        $deleted_lectures = array_filter($csv, function ($row) {
            return $row['deleted_at'] !== null;
        });

        # 削除されている列はslug, prev_lecture_slug, next_lecture_slugカラムをNULLにする
        $processed_deleted_lectures = [];
        foreach ($deleted_lectures as $lecture) {
            $lecture['slug'] = null;
            $lecture['prev_lecture_slug'] = null;
            $lecture['next)lecture_slug'] = null;
            $processed_deleted_lectures[] = $lecture;
        }

        # 存在している列にslug, prev_lecture_slug, next_lecture_slugカラムを追加する
        # lesson_id, orderをキーとしてソートする
        $lesson_id_sort_key = [];
        $order_sort_key = [];
        foreach ($existing_lectures as $index => $lecture) {
            $lesson_id_sort_key[$index] = $lecture['lesson_id'];
            $order_sort_key[$index] = $lecture['order'];
        }
        array_multisort($lesson_id_sort_key, SORT_ASC, $order_sort_key, SORT_ASC, $existing_lectures);

        # slugカラムを追加
        $lectures = [];
        foreach ($existing_lectures as $lecture) {
            $lecture['slug'] = $this->alphaID($lecture['id'], self::PAD_UP);
            $lectures[] = $lecture;
        }

        # prev_lecture_slug、next_lecture_slugカラムを追加
        $processed_lectures = [];
        foreach ($lectures as $index => $lecture) {
            $prev_lecture_slug = null;
            $next_lecture_slug = null;

            if ($index > 0) {
                $prev_lecture_slug = $lectures[$index - 1]['slug'];
            }

            if ($index < count($lectures) - 1) {
                $next_lecture_slug = $lectures[$index + 1]['slug'];
            }

            $lecture['prev_lecture_slug'] = $prev_lecture_slug;
            $lecture['next_lecture_slug'] = $next_lecture_slug;
            $processed_lectures[] = $lecture;
        }

        return array_merge($processed_lectures, $processed_deleted_lectures);
    }

    /**
     * slug用の短い英数字を数値から生成する
     *
     * 参考元
     * https://kvz.io/create-short-ids-with-php-like-youtube-or-tinyurl.html
     * https://q.hatena.ne.jp/1377468971
     *
     * 3文字以上のalphaIDが必要な場合は、下記を使用する
     * $pad_up = 3 argument
     *
     * @param int   $in
     * @param mixed $pad_up Number or boolean padds the result up to a specified length
     * @return mixed string or long
     */
    private function alphaID(int $in, $pad_up = false)
    {
        // 数値をスクランブルする
        $in *= 0x1ca7bc5b; // 奇数その1の乗算
        $in &= 0x7FFFFFFF; // 下位31ビットだけ残して正の数であることを保つ
        $in = ($in >> 15) | (($in & 0x7FFF) << 16); // ビット上下逆転
        $in *= 0x6b5f13d3; // 奇数その2（奇数その1の逆数）の乗算
        $in &= 0x7FFFFFFF;
        $in = ($in >> 15) | (($in & 0x7FFF) << 16); // ビット上下逆転
        $in *= 0x1ca7bc5b; // 奇数その1の乗算
        $in &= 0x7FFFFFFF;

        // 数値から英数字のIDを生成する
        $out = '';
        $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($index);

        if (is_numeric($pad_up)) {
            $pad_up--;

            if ($pad_up > 0) {
                $in += pow($base, $pad_up);
            }
        }

        for ($t = ($in != 0 ? floor(log($in, $base)) : 0); $t >= 0; $t--) {
            $bcp = (float) bcpow((string) $base, (string) $t);
            $a = floor($in / $bcp) % $base;
            $out = $out . substr($index, $a, 1);
            $in = $in - ($a * $bcp);
        }

        return $out;
    }
}
