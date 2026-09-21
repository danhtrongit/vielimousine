# AGENTS.md

## Quy tắc bắt buộc: tận dụng tối đa sub-agent

Áp dụng cho toàn bộ repository, với cả agent chính và các sub-agent.

- Luôn chủ động tận dụng tối đa sub-agent khi có thể giúp rút ngắn thời gian hoặc nâng cao chất lượng; không chờ người dùng yêu cầu phân việc.
- Khi bắt đầu và trong quá trình thực hiện, phân rã nhiệm vụ thành các phần độc lập. Bắt buộc giao các phần có thể làm song song cho sub-agent, chẳng hạn khảo sát code, phân tích, triển khai các module riêng, viết test và review.
- Tối đa hóa số nhánh công việc hữu ích trong giới hạn công cụ, tài nguyên và phạm vi người dùng cho phép. Không tạo agent chỉ để tăng số lượng, làm trùng việc hoặc tạo chuỗi phân cấp không cần thiết.
- Mỗi sub-agent phải được giao mục tiêu cụ thể, ngữ cảnh cần thiết, đầu ra mong đợi và phạm vi file được phép sửa. Không để nhiều agent sửa cùng file đồng thời; công việc phụ thuộc phải được phối hợp hoặc thực hiện tuần tự.
- Agent chính tiếp tục làm phần việc độc lập hữu ích trong lúc sub-agent chạy, đồng thời theo dõi tiến độ và xử lý các phụ thuộc.
- Tận dụng sub-agent để kiểm tra chéo hoặc review độc lập khi có giá trị. Agent chính phải tổng hợp, kiểm tra diff, chạy kiểm chứng phù hợp và chịu trách nhiệm về kết quả cuối cùng; không coi báo cáo của sub-agent là bằng chứng thay cho xác minh.
- Chỉ thực hiện hoàn toàn một mình khi nhiệm vụ không có phần độc lập hữu ích để giao, công cụ sub-agent không khả dụng hoặc người dùng yêu cầu không dùng. Nêu ngắn gọn lý do khi không thể áp dụng.
- Việc giao cho sub-agent không mở rộng quyền hạn: mọi agent phải giữ nguyên thay đổi có sẵn của người dùng và tuân thủ phạm vi yêu cầu cùng các hướng dẫn ưu tiên cao hơn.
