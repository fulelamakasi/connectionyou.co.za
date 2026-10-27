/**
 * CRYPTO FUNCTION FOUND ONLINE
 * AES-256-CBC helpers that match ecryptData() / decryptData() in config/bootstrap.php.
 * PHP's openssl_encrypt uses the ENCRYPTION_KEY string as raw bytes (zero-padded or
 * truncated to 32 bytes), with PKCS#7 padding and a random 16-byte IV. Web Crypto's
 * AES-CBC does the same, so both sides can read each other's payloads.
 *
 * Web Crypto only runs in a secure context: HTTPS, or http://localhost while developing.
 */
const CryptoHelper = (function () {
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    let keyPromise = null;

    function getKey() {
        if (!window.crypto || !window.crypto.subtle) {
            return Promise.reject(new Error('Secure connection required. Open this site over HTTPS (or localhost) to continue.'));
        }

        if (!keyPromise) {
            const rawKey = new Uint8Array(32);
            rawKey.set(encoder.encode(window.APP_CONFIG.encryptionKey || '').slice(0, 32));
            keyPromise = window.crypto.subtle.importKey('raw', rawKey, { name: 'AES-CBC' }, false, ['encrypt', 'decrypt']);
        }

        return keyPromise;
    }

    function bytesToBase64(bytes) {
        let binary = '';
        const chunk = 0x8000;

        for (let i = 0; i < bytes.length; i += chunk) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
        }

        return btoa(binary);
    }

    function base64ToBytes(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);

        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes;
    }

    async function encryptPayload(data) {
        const key = await getKey();
        const iv = window.crypto.getRandomValues(new Uint8Array(16));
        const plaintext = encoder.encode(JSON.stringify(data));
        const encrypted = await window.crypto.subtle.encrypt({ name: 'AES-CBC', iv: iv }, key, plaintext);

        return {
            ciphertext: bytesToBase64(new Uint8Array(encrypted)),
            iv: bytesToBase64(iv)
        };
    }

    async function decryptPayload(ciphertext, iv) {
        const key = await getKey();
        const decrypted = await window.crypto.subtle.decrypt(
            { name: 'AES-CBC', iv: base64ToBytes(iv) },
            key,
            base64ToBytes(ciphertext)
        );

        return JSON.parse(decoder.decode(decrypted));
    }

    return {
        encryptPayload: encryptPayload,
        decryptPayload: decryptPayload
    };
})();
