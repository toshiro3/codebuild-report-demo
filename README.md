# CodeBuild テストレポート可視化 検証

CodeBuild のテスト結果を可視化する2つの方法を検証するプロジェクト。

- **CodeBuild 標準レポート機能**: AWS コンソールで確認、セットアップが簡単
- **Allure Report**: リッチな UI、詳細なテスト管理

## 構成

```
.
├── buildspec-report.yml    # CodeBuild標準レポート用
├── buildspec-allure.yml    # Allure Report用
├── phpunit.xml             # Allure Extension設定済み
├── allure-config.json      # Allure設定
├── composer.json
└── tests/
    └── Unit/
        ├── UserServiceTest.php
        └── CalculatorTest.php
```

## 検証手順

### 1. 事前準備

#### IAMロールの作成

```bash
cat > trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "codebuild.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

aws iam create-role \
  --role-name codebuild-report-demo-role \
  --assume-role-policy-document file://trust-policy.json

cat > codebuild-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "codebuild:CreateReportGroup",
        "codebuild:CreateReport",
        "codebuild:UpdateReport",
        "codebuild:BatchPutTestCases"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:ListBucket",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::your-allure-report-bucket",
        "arn:aws:s3:::your-allure-report-bucket/*"
      ]
    }
  ]
}
EOF

aws iam put-role-policy \
  --role-name codebuild-report-demo-role \
  --policy-name codebuild-report-demo-policy \
  --policy-document file://codebuild-policy.json
```

#### S3バケットの作成（Allure用）

> ⚠️ **セキュリティに関する注意**
> テスト結果にはクラス名やエラーログに含まれる環境変数など、機密情報が混じる可能性があります。社内プロジェクトで利用する場合は、CloudFront + OAC と WAF や IP 制限を組み合わせる、あるいは VPN 内からのみアクセス可能な設定を推奨します。

```bash
BUCKET_NAME="your-allure-report-bucket"

aws s3 mb "s3://${BUCKET_NAME}"

# 静的ウェブサイトホスティングを有効化
aws s3 website "s3://${BUCKET_NAME}" \
  --index-document index.html

# バケットポリシー（パブリックアクセスする場合）
cat > bucket-policy.json << EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::${BUCKET_NAME}/report/*"
    }
  ]
}
EOF

aws s3api put-bucket-policy \
  --bucket "${BUCKET_NAME}" \
  --policy file://bucket-policy.json
```

### 2. CodeBuildプロジェクトの作成

```bash
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

# CodeBuild標準レポート用
aws codebuild create-project \
  --name codebuild-report-standard \
  --source '{
    "type": "GITHUB",
    "location": "https://github.com/<your-username>/codebuild-report-demo",
    "buildspec": "buildspec-report.yml"
  }' \
  --artifacts '{"type": "NO_ARTIFACTS"}' \
  --environment '{
    "type": "LINUX_CONTAINER",
    "image": "aws/codebuild/amazonlinux-x86_64-standard:5.0",
    "computeType": "BUILD_GENERAL1_SMALL"
  }' \
  --service-role "arn:aws:iam::${AWS_ACCOUNT_ID}:role/codebuild-report-demo-role"

# Allure Report用
aws codebuild create-project \
  --name codebuild-report-allure \
  --source '{
    "type": "GITHUB",
    "location": "https://github.com/<your-username>/codebuild-report-demo",
    "buildspec": "buildspec-allure.yml"
  }' \
  --artifacts '{"type": "NO_ARTIFACTS"}' \
  --environment '{
    "type": "LINUX_CONTAINER",
    "image": "aws/codebuild/amazonlinux-x86_64-standard:5.0",
    "computeType": "BUILD_GENERAL1_SMALL",
    "environmentVariables": [
      {
        "name": "ALLURE_BUCKET",
        "value": "your-allure-report-bucket"
      }
    ]
  }' \
  --service-role "arn:aws:iam::${AWS_ACCOUNT_ID}:role/codebuild-report-demo-role"
```

### 3. 検証実行

#### CodeBuild標準レポート

```bash
aws codebuild start-build --project-name codebuild-report-standard
```

確認: CodeBuild コンソール → プロジェクト → レポート

#### Allure Report

```bash
aws codebuild start-build --project-name codebuild-report-allure
```

確認: `http://your-allure-report-bucket.s3-website-ap-northeast-1.amazonaws.com/report/index.html`

### 4. 履歴確認（Allure）

複数回ビルドを実行して、トレンドグラフを確認

```bash
# 2回目
aws codebuild start-build --project-name codebuild-report-allure

# 3回目
aws codebuild start-build --project-name codebuild-report-allure
```

※ Allure の履歴は最大20件に制限されています

### 5. クリーンアップ

```bash
# プロジェクト削除
aws codebuild delete-project --name codebuild-report-standard
aws codebuild delete-project --name codebuild-report-allure

# レポートグループ削除
aws codebuild delete-report-group \
  --arn "arn:aws:codebuild:ap-northeast-1:$(aws sts get-caller-identity --query Account --output text):report-group/codebuild-report-standard-phpunit-reports" \
  --delete-reports

# S3バケット削除
aws s3 rb "s3://your-allure-report-bucket" --force

# IAMロール削除
aws iam delete-role-policy \
  --role-name codebuild-report-demo-role \
  --policy-name codebuild-report-demo-policy

aws iam delete-role --role-name codebuild-report-demo-role

# CloudWatch Logs 削除
aws logs delete-log-group --log-group-name "/aws/codebuild/codebuild-report-standard"
aws logs delete-log-group --log-group-name "/aws/codebuild/codebuild-report-allure"

rm -f trust-policy.json codebuild-policy.json bucket-policy.json
```

## 比較

| 項目 | CodeBuild標準 | Allure Report |
|-----|--------------|---------------|
| セットアップ | ◎ 簡単 | △ やや複雑 |
| レポート保持 | 30日（自動削除） | S3 に保存（設定次第） |
| 履歴・トレンド | 簡易的（成功率・実行時間の推移） | 詳細（最大20件） |
| UI の詳細度 | 基本的 | リッチ |
| 費用 | 無料（ビルド料金に含まれる） | S3 費用のみ |
| 並列結果の結合 | 別々に表示 | 自動で統合 |
