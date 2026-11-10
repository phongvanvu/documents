<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Nơi Ban Hành</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body>
    <div class="container mt-5">
        <h2>Thêm Nơi Ban Hành</h2>

        <!-- Hiển thị thông báo lỗi hoặc thành công -->
        <?php if (!empty($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
        <?php if (!empty($success)) { echo "<div class='alert alert-success'>$success</div>"; } ?>

        <!-- Form Thêm Nơi Ban Hành -->
        <form id="addDepartmentForm" action="add_department_process.php" method="POST">
            <div class="mb-3">
                <label for="authority_name" class="form-label">Tên Cơ Quan Ban Hành</label>
                <input type="text" class="form-control" id="authority_name" name="authority_name" required>
            </div>
            <button type="submit" class="btn btn-primary">Lưu</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>
</html>
