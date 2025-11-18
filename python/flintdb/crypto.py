class Crypto:
    """Utility class for handling all cryptographic operations."""
    @staticmethod
    def kek(kek: str) -> bytes | None:
        """Normalizes kek for use with Fernet.

        Returns:
            None if kek is empty, base64 encoded sha256 of kek otherwise.
        """
        if not kek:
            return None

        import hashlib, base64
        sha256_object = hashlib.sha256(kek.encode("utf-8"))
        return base64.b64encode(sha256_object.digest())

    @staticmethod
    def random_dek(kek: bytes) -> str:
        """Randomly generates Data Encryption Key (DEK).

        Args:
            kek: The Key Encryption Key (KEK) to encrypt the DEK with.

        Returns:
            Generated and encrypted Data Encryption Key (DEK).
        """
        from cryptography.fernet import Fernet
        dek = Fernet.generate_key().decode("utf-8")
        return Crypto.encrypt(dek, kek)

    @staticmethod
    def encrypt(data: "Any", kek: bytes) -> str:
        """Encrypts data using provided KEK.

        Args:
            data: The data to encrypt.
            kek: The key to be used for encryption.

        Returns:
            The ciphertext.
        """
        import json
        from cryptography.fernet import Fernet

        fernet = Fernet(kek)
        json_data = json.dumps(data).encode("utf-8")
        value = fernet.encrypt(json_data)
        return value.decode("utf-8")

    @staticmethod
    def decrypt(cipher: str, kek: bytes) -> "Any":
        """Decrypts a cipher string back to into data.

        Args:
            cipher: The encrypted data string.
            kek: The key used for decryption.

        Returns:
            The original data.
        """
        import json
        from cryptography.fernet import Fernet, InvalidToken

        fernet = Fernet(kek)
        value = fernet.decrypt(data.encode("utf-8"))
        return json.loads(value.decode("utf-8"))

    @staticmethod
    def random_id(nbytes: int | None = None) -> str:
        """Returns random string of nbytes*2 size."""
        import secrets
        return secrets.token_hex(nbytes)

    @staticmethod
    def hash(data: "Any") -> str:
        """Returns string hash of data."""
        import pickle, xxhash
        bytes = pickle.dumps(data)
        hash_object = xxhash.xxh64(bytes)
        return hash_object.hexdigest()
