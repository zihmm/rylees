class ValidationError(Exception):
    pass

class Validator:
    def validate(self, text: str) -> None:
        stripped = text.strip()
        if not stripped:
            raise ValidationError("Response is empty or whitespace")
        if len(stripped) < 10:
            raise ValidationError("Response too short (min 10 chars)")
        if len(stripped) > 2000:
            raise ValidationError("Response too long (max 2000 chars)")
