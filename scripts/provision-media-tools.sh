#!/bin/sh

set -eu

repository_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ffmpeg_version="8.1.2"
release_fingerprint="FCF986EA15E6E293A5644F10B4322F04D67658D8"
install_root="${repository_root}/tmp/toolchain/ffmpeg-${ffmpeg_version}"
ffprobe_binary="${install_root}/bin/ffprobe"

if [ -x "${ffprobe_binary}" ] && "${ffprobe_binary}" -version 2>/dev/null | grep -q "^ffprobe version ${ffmpeg_version}"; then
    printf '%s\n' "ffprobe ${ffmpeg_version} is ready at ${ffprobe_binary}"
    exit 0
fi

for required_command in curl gpg make gcc pkg-config tar xz; do
    if ! command -v "${required_command}" >/dev/null 2>&1; then
        printf '%s\n' "Missing required command: ${required_command}" >&2
        exit 1
    fi
done

if [ -e "${install_root}" ]; then
    printf '%s\n' "Refusing to overwrite incomplete or unexpected toolchain path: ${install_root}" >&2
    printf '%s\n' "Move that exact ignored directory aside and rerun this command." >&2
    exit 1
fi

build_root=$(mktemp -d "${TMPDIR:-/tmp}/global-english-ffprobe.XXXXXX")
keyring_root="${build_root}/gnupg"
archive="${build_root}/ffmpeg-${ffmpeg_version}.tar.xz"
signature="${archive}.asc"
signing_key="${build_root}/ffmpeg-devel.asc"

cleanup() {
    rm -rf -- "${build_root}"
}

trap cleanup EXIT HUP INT TERM
mkdir -m 700 "${keyring_root}"

curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 \
    --connect-timeout 20 --max-time 180 --retry 3 --retry-delay 2 --retry-all-errors \
    --output "${archive}" \
    "https://ffmpeg.org/releases/ffmpeg-${ffmpeg_version}.tar.xz"
curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 \
    --connect-timeout 20 --max-time 180 --retry 3 --retry-delay 2 --retry-all-errors \
    --output "${signature}" \
    "https://ffmpeg.org/releases/ffmpeg-${ffmpeg_version}.tar.xz.asc"
curl --fail --location --silent --show-error --proto '=https' --tlsv1.2 \
    --connect-timeout 20 --max-time 180 --retry 3 --retry-delay 2 --retry-all-errors \
    --output "${signing_key}" \
    "https://ffmpeg.org/ffmpeg-devel.asc"

actual_fingerprint=$(
    gpg --homedir "${keyring_root}" --batch --with-colons \
        --show-keys --fingerprint "${signing_key}" \
        | awk -F: '$1 == "fpr" { print $10; exit }'
)

if [ "${actual_fingerprint}" != "${release_fingerprint}" ]; then
    printf '%s\n' "FFmpeg release-key fingerprint mismatch." >&2
    exit 1
fi

gpg --homedir "${keyring_root}" --batch --import "${signing_key}"
gpg --homedir "${keyring_root}" --batch --verify "${signature}" "${archive}"

tar --extract --xz --file "${archive}" --directory "${build_root}"
cd "${build_root}/ffmpeg-${ffmpeg_version}"

./configure \
    --prefix="${install_root}" \
    --disable-autodetect \
    --disable-debug \
    --disable-doc \
    --disable-network \
    --disable-x86asm \
    --disable-programs \
    --enable-ffprobe \
    --enable-static \
    --disable-shared

build_jobs=$(getconf _NPROCESSORS_ONLN 2>/dev/null || printf '2')
case "${build_jobs}" in
    ''|*[!0-9]*) build_jobs=2 ;;
esac
if [ "${build_jobs}" -gt 8 ]; then
    build_jobs=8
fi

make -j "${build_jobs}" ffprobe
make install

if ! "${ffprobe_binary}" -version | grep -q "^ffprobe version ${ffmpeg_version}"; then
    printf '%s\n' "Built ffprobe did not report the pinned version." >&2
    exit 1
fi

printf '%s\n' "ffprobe ${ffmpeg_version} is ready at ${ffprobe_binary}"
printf '%s\n' "Set MEDIA_FFPROBE_BINARY=${ffprobe_binary} with MEDIA_INSPECTOR_DRIVER=ffprobe."
