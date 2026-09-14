# Đồng bộ dữ liệu Facebook trên máy

Kho dữ liệu cục bộ chạy bằng **Node.js 24+**, sử dụng SQLite tích hợp, không cần cài npm dependencies hay khởi động PHP. Đây là giao diện bổ sung độc lập trong dự án; dữ liệu chưa được nhập vào các bảng analytics của Laravel.

## Sử dụng

Chạy tại thư mục dự án:

```powershell
npm run data:sync -- "D:\Data\your_facebook_activity"
npm run data:serve
```

Mở <http://127.0.0.1:4318>. Trang mở thẳng vào tìm kiếm tin nhắn; thanh bên chuyển giữa hội thoại, hoạt động, file nguồn và tổng quan. Chạy lại lệnh serve nếu đã đóng tiến trình hoặc khởi động lại máy. Nếu đang mở giao diện cũ, tải lại trang.

### Bộ lọc và tìm kiếm

- Gõ từ khóa có dấu hoặc không dấu, không phân biệt hoa thường, bao gồm `đ/d`. Chọn chứa tất cả từ, bất kỳ từ hoặc cụm từ liền nhau. Chế độ tất cả/bất kỳ khớp cả tiền tố từ; chế độ cụm từ khớp chuỗi từ đầy đủ liền nhau. Dấu câu được tách theo từ. Tìm kiếm tin nhắn xét nội dung và tên người gửi; tìm hoạt động xét bản ghi JSON.
- Có thể nhập từ loại trừ và kết hợp khoảng ngày, hội thoại, tên người gửi chính xác, thư mục Messenger, ảnh/video/âm thanh/tệp/GIF/liên kết, cảm xúc hoặc thông tin thiếu/trùng.
- Gõ tên không dấu trong ô Người gửi rồi chọn tên chính xác từ gợi ý; danh sách được giới hạn 100 tên phù hợp mỗi lần. Tên trùng trong bản xuất không chứng minh cùng một người.
- Hoạt động lọc theo nhóm, loại bản ghi, file nguồn và trạng thái thiếu thời gian. Hội thoại lọc theo tên, số tin tối thiểu và ngày của tin nhắn cuối cùng.
- Nhấn **Áp dụng** hoặc **Tìm kiếm** để dùng toàn bộ điều kiện đang nhập. Các nhãn phía trên kết quả cho phép bỏ riêng từng điều kiện; **Đặt lại** xóa bộ lọc của tab hiện tại.
- Khoảng ngày bao gồm trọn ngày kết thúc. Chọn múi giờ trình duyệt hoặc UTC. Biểu đồ tổng hợp theo UTC; bấm cột tháng sẽ tự chọn UTC để số kết quả khớp với biểu đồ.
- Sắp xếp mới nhất/cũ nhất; hội thoại có thêm số tin giảm dần. Phân trang 50 bản ghi, tối đa 20 từ cho mỗi truy vấn từ khóa hoặc loại trừ.

### Đọc theo ngữ cảnh và lưu bộ lọc

Từ kết quả, chọn **Xem trong hội thoại** để mở tối đa 20 tin trước và 20 tin sau tin đang chọn. Các tin ngữ cảnh không bị giới hạn bởi bộ lọc tìm kiếm. Tin đang xem được đánh dấu, có đường dẫn file nguồn và vị trí bản ghi; nút trước/sau cho phép đọc tiếp. Đóng cửa sổ để trở lại đúng trang kết quả. Nội dung dài có nút **Đọc đầy đủ**; thông tin gốc có trong **Bản ghi JSON**.

Chọn **Lưu bộ lọc**, đặt tên để lưu các điều kiện đã áp dụng trên trình duyệt hiện tại. Tối đa 20 bộ lọc; tên trùng sẽ thay thế bộ lọc cũ. Thanh bên cho phép áp dụng hoặc xóa bộ lọc đã lưu. Bộ lọc không đồng bộ sang máy khác. Tab, trang kết quả và bộ lọc hiện tại được giữ trong phiên trình duyệt. Việc lưu bộ lọc cũng lưu các từ khóa/tên đã chọn trong bộ nhớ trình duyệt.

Trong **Tổng quan**, bấm tháng, nhóm hoạt động hoặc số bản ghi thiếu thông tin để mở danh sách tương ứng.

Chạy lại lệnh sync sau khi thay dữ liệu xuất trong cùng thư mục. Đây là đồng bộ theo yêu cầu; không tự tải dữ liệu mới từ Facebook và không chạy theo lịch. File không đổi được bỏ qua theo SHA-256. Bản ghi của file thay đổi được cập nhật; file không còn trong nguồn được loại khỏi bản sao SQLite. Công cụ không chỉnh sửa hoặc xóa file trong thư mục nguồn. Một cơ sở dữ liệu chỉ nhận một thư mục nguồn; dùng `--db` để tạo kho riêng cho nguồn khác.

```powershell
node scripts/facebook-local.mjs "D:\Data\your_facebook_activity" --db "storage/app/private/facebook-local/activity.sqlite"
node scripts/facebook-local-server.mjs --port 4318
npm run data:test
```

## Dữ liệu và cách đếm

- `storage/app/private/facebook-local/activity.sqlite`: kho dữ liệu thật, gồm nội dung tin nhắn và JSON gốc nén gzip. Thư mục này được loại khỏi Git.
- `sync-report.json` cùng thư mục: kết quả đồng bộ gần nhất và thống kê tổng hợp.
- `source_files`: danh mục file, hash, dung lượng, cấu trúc, số bản ghi và toàn bộ byte JSON gốc trong `raw_gzip`.
- `messages`: tin nhắn duy nhất; `message_sources` liên kết mỗi bản ghi gốc với tin nhắn đã chuẩn hóa.
- `activities`: các phần tử mảng cấp đầu hoặc đối tượng JSON riêng lẻ; cấu trúc chưa chuyên biệt hóa vẫn được giữ trong `payload_json`. Số bản ghi là số phần tử được trích xuất, không nhất thiết là số sự kiện xã hội riêng biệt.
- `conversations`, `monthly_messages`, `activity_categories`: SQL views dành cho phân tích.
- `sync_runs`: lịch sử thành công/thất bại; lỗi đọc/JSON gây rollback toàn bộ lần đồng bộ và giữ nguyên snapshot trước đó.
- `message_search`, `activity_search`: chỉ mục FTS5 đã chuẩn hóa dấu; tự tạo khi mở kho cũ và được cập nhật cùng giao dịch nhập/xóa. Không cần nhập lại file nguồn để nâng cấp tìm kiếm.

Tin nhắn được gộp theo mã thư mục hội thoại trong `thread_path`, không theo tên hiển thị. Các file `message_1.json`, `message_2.json` của cùng hội thoại được hợp nhất. Fingerprint gồm hội thoại, người gửi, thời gian, nội dung và trường phương tiện; hai bản tin có toàn bộ các trường này giống nhau được tính là một tin nhắn. Mọi vị trí xuất hiện vẫn được lưu trong `message_sources`. Tin nhắn không có tên người gửi vẫn được giữ, không suy đoán danh tính.

Sửa lỗi mã hóa UTF-8/Latin-1 cho bản chuẩn hóa; JSON gốc không bị biến đổi. Số nguyên lớn ngoài giới hạn chính xác của JavaScript được giữ dưới dạng chuỗi. Thời gian được chuẩn hóa UTC; giao diện hiển thị theo múi giờ trình duyệt. Giá trị thời gian bằng 0 hoặc thiếu được coi là chưa rõ. Biểu đồ hiển thị tối đa 36 tháng có dữ liệu gần nhất, chưa suy luận độ đầy đủ của bản xuất.

Chỉ JSON bên trong thư mục được chọn được đồng bộ. Ảnh/video chỉ giữ thông tin tham chiếu, không sao chép hoặc tải từ mạng. Hồ sơ và danh sách bạn bè ở các thư mục ngang cấp không thuộc lần nhập này. Các hoạt động không có tác giả/đối tượng rõ ràng không được dùng để suy luận mức độ thân thiết hoặc ai đã tương tác với chủ tài khoản.

Giao diện chỉ lắng nghe `127.0.0.1`, chỉ cung cấp API đọc, chặn Origin/Host ngoài địa chỉ cục bộ và không dùng CDN. Kho SQLite chứa nội dung riêng tư dạng đọc được, không mã hóa; đây là công cụ cá nhân trên máy, chưa có phân quyền nhiều người dùng như Laravel.

## Trạng thái Laravel

PHP của máy hiện bị Windows Application Control chặn các extension, trong đó có OpenSSL và PDO SQLite. Composer không cài được dependencies nên chưa thể chạy Laravel và bộ kiểm thử PHP. Kho Node/SQLite độc lập đáp ứng việc tra cứu dữ liệu ngay; không đổi chính sách bảo vệ Windows để chạy PHP.
