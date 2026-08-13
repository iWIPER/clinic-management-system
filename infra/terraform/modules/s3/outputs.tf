output "bucket_name" {
  value = aws_s3_bucket.app_storage.bucket
}

output "bucket_arn" {
  value = aws_s3_bucket.app_storage.arn
}
