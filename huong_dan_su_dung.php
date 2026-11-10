<?php
// Khởi động session
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username']) && isset($_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['full_name'] = $_COOKIE['full_name'] ?? '';
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
        $_SESSION['avatar'] = $_COOKIE['avatar'] ?? '';
    } else {
        header('Location: login.php');
        exit;
    }
}

$user_role = $_SESSION['role'] ?? 'viewer';
$title = "Hướng dẫn sử dụng";

include 'header.php'; // Bao gồm header
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        
        <!-- Nội dung chính -->
        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-5" style="max-width: 1000px; margin: 0 auto;">
                
                <div class="text-center mb-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger text-white rounded-circle mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fas fa-book-open fa-2x"></i>
                    </div>
                    <h2 class="fw-bold text-dark">HƯỚNG DẪN SỬ DỤNG HỆ THỐNG</h2>
                    <p class="text-muted">Tài liệu tham khảo toàn diện các tính năng trên Hệ thống Quản lý Văn bản</p>
                </div>

                <div class="accordion shadow-sm" id="manualAccordion">

                    <!-- PHẦN 1: GIỚI THIỆU & PHÂN QUYỀN -->
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                <i class="fas fa-users-cog me-2"></i> 1. Giới thiệu & Phân quyền tài khoản
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <p>Hệ thống hỗ trợ 3 nhóm quyền hạn chính:</p>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item">
                                        <span class="badge bg-danger me-2" style="width: 70px;">Admin</span>
                                        Có toàn quyền hệ thống: Quản lý người dùng, Danh mục, Rà soát dữ liệu, Sao lưu và mọi quyền của Editor.
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-primary me-2" style="width: 70px;">Editor</span>
                                        Được phép Thêm mới, Cập nhật (Sửa) và Xóa các văn bản pháp luật, tài liệu đính kèm nhưng không được can thiệp vào cấu hình hệ thống.
                                    </li>
                                    <li class="list-group-item">
                                        <span class="badge bg-secondary me-2" style="width: 70px;">Viewer</span>
                                        Chỉ có quyền Tra cứu, Xem văn bản, tải file và xem thống kê cá nhân. Không thể thêm hay sửa đổi dữ liệu.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- PHẦN 2: THÊM & SỬA VĂN BẢN (DÀNH CHO ADMIN/EDITOR) -->
                    <?php if (in_array($user_role, ['admin', 'editor'])): ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                <i class="fas fa-edit me-2"></i> 2. Quản lý Văn bản, Phụ lục & Liên kết
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <p>Hệ thống cho phép bạn quản lý văn bản một cách toàn diện đa chiều:</p>
                                <ul>
                                    <li><strong>Thêm văn bản mới:</strong> Khai báo số ký hiệu, ngày ban hành, trích yếu và tải lên file văn bản chính (<code>add_document.php</code>).</li>
                                    <li><strong>Thêm danh mục nhanh:</strong> Ngay tại màn hình thêm văn bản, bạn có thể nhấn nút <button class="btn btn-outline-danger btn-sm px-1 py-0"><i class="fas fa-plus"></i></button> để thêm mới Loại văn bản hoặc Cơ quan ban hành mà không cần rời trang (Công nghệ AJAX xử lý ngầm).</li>
                                    <li><strong>Tài liệu đính kèm (Phụ lục):</strong> Nếu văn bản có nhiều bản scan hoặc phụ lục đi kèm, bạn có thể bổ sung thông qua tính năng mở rộng (<code>add_document_attachment.php</code>).</li>
                                    <li><strong>Văn bản liên quan:</strong> Liên kết các văn bản lại với nhau (ví dụ: Văn bản này sửa đổi, bổ sung, thay thế cho văn bản nào) (<code>add_document_relation.php</code>).</li>
                                </ul>
                                <div class="alert alert-warning border-warning mt-3">
                                    <strong><i class="fas fa-lock me-1"></i> Lưu ý đối với VĂN BẢN MẬT:</strong><br>
                                    Nếu Số ký hiệu bạn nhập có chứa cụm từ <code>-M</code>, <code>/M</code>, hoặc <code>MẬT</code>, hệ thống sẽ <strong>tự động vô hiệu hóa tính năng tải file đính kèm vật lý lên server</strong> nhằm đảm bảo an toàn thông tin tuyệt đối theo quy định bảo mật.
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PHẦN 3: TRA CỨU VÀ TÌM KIẾM -->
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                <i class="fas fa-search me-2"></i> 3. Tra cứu, Lọc dữ liệu & Xuất báo cáo
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <p>Tại mục <strong>Danh sách văn bản</strong> (<code>documents.php</code>), hệ thống cung cấp bộ máy tìm kiếm mạnh mẽ:</p>
                                <ul>
                                    <li><strong>Tìm kiếm từ khóa:</strong> Tìm đa luồng theo Số ký hiệu, Trích yếu, hoặc Ghi chú.</li>
                                    <li><strong>Lọc theo bộ tiêu chí nâng cao:</strong> Lọc khoảng thời gian (Từ ngày... Đến ngày...), Loại văn bản, Cơ quan ban hành và Tình trạng hiệu lực.</li>
                                    <li><strong>Bộ lọc nhanh (Quick Filters):</strong> Các nút bấm tích hợp sẵn cho phép lọc ngay lập tức các văn bản: <em>Chỉ có PDF, Có Word, Có ghi chú, Quan trọng, hoặc Chưa có file...</em></li>
                                    <li><strong>Xuất Excel:</strong> Nhấn nút "Xuất Excel", hệ thống sẽ trích xuất toàn bộ dữ liệu đang được hiển thị trên bảng (sau khi đã lọc) thành file <code>danh_sach_van_ban.xlsx</code> định dạng chuẩn.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- PHẦN 4: CHỦ ĐỀ & THỐNG KÊ -->
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                <i class="fas fa-chart-pie me-2"></i> 4. Quản lý Chủ đề (Tags) & Thống kê
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <ul>
                                    <li><strong>Chủ đề cá nhân (Tags):</strong> Khi cập nhật văn bản, bạn có thể gán các Tag (ngăn cách bằng dấu phẩy). Trang <em>Chủ đề cá nhân</em> (<code>topics.php</code>) sẽ phân nhóm văn bản theo Tag này giống như các thư mục ảo, giúp bạn quản lý kho tài liệu số khoa học hơn.</li>
                                    <li><strong>Đánh dấu quan trọng:</strong> Chức năng ghim sao vàng <i class="fas fa-star text-warning"></i> giúp bạn lưu và mở nhanh các văn bản thường xuyên sử dụng.</li>
                                    <li><strong>Bảng tổng quan & Thống kê:</strong> Trang Tổng quan (<code>dashboard.php</code>) và Thống kê cá nhân (<code>personal_stats.php</code>) cung cấp các biểu đồ trực quan, giúp bạn kiểm soát kho dữ liệu và biết được hệ thống đang thiếu file hay ghi chú ở đâu để kịp thời bổ sung.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- PHẦN 5: QUẢN LÝ DANH MỤC TỪ ĐIỂN -->
                    <?php if (in_array($user_role, ['admin', 'editor'])): ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingFive">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                <i class="fas fa-tags me-2"></i> 5. Quản lý Danh mục (Từ điển dữ liệu)
                            </button>
                        </h2>
                        <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <p>Hệ thống chuẩn hóa việc nhập liệu thông qua các danh mục từ điển:</p>
                                <ul>
                                    <li><strong>Loại văn bản:</strong> Quản lý danh mục các dạng văn bản (Nghị định, Quyết định, Thông tư...) (<code>add_document_type.php</code>).</li>
                                    <li><strong>Cơ quan ban hành:</strong> Quản lý danh mục các tổ chức ban hành từ Trung ương đến cấp Sở/Ngành (<code>add_issuing_authority.php</code>).</li>
                                    <li><strong>Phòng ban & Quy trình:</strong> Thiết lập cơ cấu tổ chức và quy trình luân chuyển nội bộ (<code>add_department_process.php</code>).</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PHẦN 6: CÔNG CỤ QUẢN TRỊ (ADMIN) -->
                    <?php if ($user_role === 'admin'): ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="headingSix">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                <i class="fas fa-cogs me-2"></i> 6. Quản trị & Bảo trì Hệ thống (Dành cho Admin)
                            </button>
                        </h2>
                        <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <p>Các tính năng nâng cao giúp đảm bảo hệ thống vận hành trơn tru và an toàn:</p>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-user-plus me-2 text-primary"></i>Quản lý người dùng:</strong> Thêm mới thành viên, phân quyền (Admin, Editor, Viewer) và hỗ trợ đổi mật khẩu (<code>add_user.php</code>).
                                    </li>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-search-plus me-2 text-warning"></i>Rà soát dữ liệu:</strong> Quét tự động ổ cứng để phát hiện các bản ghi bị "mồ côi" (mất file) hoặc các file rác đang tồn tại do quá trình xóa lỗi trước đây. Chức năng dọn dẹp hàng loạt giúp giải phóng tối đa bộ nhớ (<code>audit_documents.php</code>).
                                    </li>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-database me-2 text-success"></i>Sao lưu an toàn:</strong> Khởi tạo các bản sao lưu Database định kỳ và kiểm tra trạng thái các bản Backup để phòng ngừa rủi ro (<code>backup.php</code>, <code>check_backup.php</code>).
                                    </li>
                                    <li class="list-group-item">
                                        <strong><i class="fas fa-exchange-alt me-2 text-info"></i>Chuyển đổi dữ liệu:</strong> Công cụ hỗ trợ đồng bộ, Import/Export hoặc Migrate dữ liệu lớn (<code>chuyendulieu.php</code>).
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PHẦN 7: TÀI KHOẢN & LIÊN HỆ -->
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header" id="headingSeven">
                            <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                                <i class="fas fa-user-shield me-2"></i> 7. Tài khoản & Liên hệ hỗ trợ
                            </button>
                        </h2>
                        <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#manualAccordion">
                            <div class="accordion-body">
                                <ul>
                                    <li><strong>Hồ sơ cá nhân:</strong> Bạn có thể vào xem và chỉnh sửa Avatar, thông tin liên lạc.</li>
                                    <li><strong>Đổi mật khẩu:</strong> Khuyến nghị thay đổi mật khẩu định kỳ (<code>change_password.php</code>). Sau khi thực hiện đổi, hệ thống sẽ yêu cầu bạn đăng nhập lại.</li>
                                    <li><strong>Liên hệ hỗ trợ:</strong> Nếu bạn quên mật khẩu hoặc gặp lỗi phần mềm, bạn có thể sử dụng chức năng gửi thông điệp cho Quản trị viên (<code>contact.php</code>). Yêu cầu sẽ được chuyển về trang quản trị yêu cầu liên hệ.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div> <!-- End Accordion -->

                <div class="text-center mt-5">
                    <p class="text-muted fw-semibold">
                        <i class="fas fa-headset me-2"></i>Cần hỗ trợ thêm? Vui lòng liên hệ Bộ phận IT / Quản trị viên hệ thống.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>