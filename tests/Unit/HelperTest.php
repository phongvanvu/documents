<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * Kiểm tra hàm loại bỏ dấu tiếng Việt.
     */
    public function test_remove_vietnamese_accents(): void
    {
        $str = "Quyết định số 123/QĐ-BCA về phân cấp quản lý";
        $cleaned = remove_vietnamese_accents($str);
        $this->assertEquals("Quyet dinh so 123/QD-BCA ve phan cap quan ly", $cleaned);
    }

    /**
     * Kiểm tra hàm chuẩn hóa tên tệp tải lên (chống lỗi MAX_PATH và ký tự đặc biệt).
     */
    public function test_sanitize_upload_filename(): void
    {
        $longName = "Quyết_định_số_377-QĐ_TW_ngày_8_10_2025_về_phân_cấp_quản_lý_cán_bộ_và_quy_hoạch_bổ_nhiệm_giới_thiệu_ứng_cử_tạm_đình_chỉ_công_tác_cho_thôi_giữ_chức_vụ_từ_chức_miễn_nhiệm_đối_với_cán_bộ.pdf";
        $safeName = sanitize_upload_filename($longName, 80);

        // Đảm bảo không còn dấu tiếng Việt, không quá 80 ký tự cơ bản, giữ nguyên phần mở rộng
        $this->assertStringEndsWith('.pdf', $safeName);
        $this->assertLessThanOrEqual(84, strlen($safeName));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+\.pdf$/', $safeName);
    }

    /**
     * Kiểm tra định dạng dung lượng byte thành đơn vị người đọc.
     */
    public function test_format_bytes(): void
    {
        $this->assertEquals('0 B', format_bytes(0));
        $this->assertEquals('1 KB', format_bytes(1024));
        $this->assertEquals('1 MB', format_bytes(1024 * 1024));
        $this->assertEquals('2.5 MB', format_bytes((int)(2.5 * 1024 * 1024)));
    }
}
