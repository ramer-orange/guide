FROM public.ecr.aws/amazonlinux/amazonlinux:2023-minimal

# バージョンを上げるときは EMULATOR_VERSION と下の2つのハッシュをまとめて更新する。
# ハッシュが空のままだと、ダウンロードした RPM の実際の値を表示して止まる。
ARG EMULATOR_VERSION=1.0.341-1
ARG EMULATOR_ARCH
ARG EMULATOR_SHA256_X86_64=
ARG EMULATOR_SHA256_AARCH64=

# 外部から取ってきた RPM をそのまま root でインストールするので、配布元の差し替えに
# 気づけるよう SHA-256 を固定する。ADD --checksum はアーキテクチャごとに値を切り替え
# られないため、RUN の中で照合する。
ADD https://downloads.aws.com/aws-emulator/packages/aws-emulator/LTS/aws-emulator-${EMULATOR_VERSION}-${EMULATOR_ARCH}.rpm /tmp/aws-emulator.rpm
RUN case "$EMULATOR_ARCH" in \
        x86_64) expected="$EMULATOR_SHA256_X86_64" ;; \
        aarch64) expected="$EMULATOR_SHA256_AARCH64" ;; \
        *) echo "Unsupported EMULATOR_ARCH: $EMULATOR_ARCH" >&2; exit 1 ;; \
    esac \
    && actual=$(sha256sum /tmp/aws-emulator.rpm | cut -d ' ' -f 1) \
    && if [ "$actual" != "$expected" ]; then \
        echo "aws-emulator-${EMULATOR_VERSION}-${EMULATOR_ARCH}.rpm の SHA-256 が一致しません" >&2; \
        echo "  expected: ${expected:-(未設定)}" >&2; \
        echo "  actual:   $actual" >&2; \
        exit 1; \
    fi \
    && rpm -i /tmp/aws-emulator.rpm && rm /tmp/aws-emulator.rpm \
    && mkdir -p /home/emulator && chown 1000 /home/emulator

USER 1000
ENV HOME=/home/emulator EMULATOR_TELEMETRY=off
ENTRYPOINT ["aws-emulator", "-b", "0.0.0.0"]
