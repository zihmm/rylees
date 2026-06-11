import pytest
from app.validator import Validator, ValidationError


def test_raises_on_empty_string():
    with pytest.raises(ValidationError):
        Validator().validate("")


def test_raises_on_whitespace_only():
    with pytest.raises(ValidationError):
        Validator().validate("   \n\t  ")


def test_raises_on_too_short():
    with pytest.raises(ValidationError):
        Validator().validate("hi")


def test_raises_on_too_long():
    with pytest.raises(ValidationError):
        Validator().validate("x" * 2001)


def test_passes_valid_text():
    Validator().validate("x" * 50)


def test_does_not_reject_french_text():
    text = "Cette version améliore l'expérience utilisateur avec de nouvelles fonctionnalités."
    Validator().validate(text)
