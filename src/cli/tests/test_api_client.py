import json
import httpx
import pytest
from app.api_client import ApiClient

PROJECT_JSON = {
    "id": "proj-1",
    "name": "Member Portal",
    "key": "member-portal",
    "description": "A customer-facing portal for membership management.",
    "customer": {
        "id": "cust-1",
        "name": "Acme Ltd.",
        "industry": "Architecture",
    },
    "llm": {
        "temperature": 0.5,
        "tonality": "professional",
    },
}


def test_get_project_returns_project_config(httpx_mock):
    httpx_mock.add_response(
        method="GET",
        url="https://api.rylees.ai/v1/projects/tok123",
        json=PROJECT_JSON,
    )
    client = ApiClient(api_token="api-tok")
    config = client.get_project("tok123")
    assert config["id"] == "proj-1"
    assert config["customer_name"] == "Acme Ltd."
    assert config["customer_industry"] == "Architecture"
    assert config["llm_temperature"] == 0.5
    assert config["llm_tonality"] == "professional"


def test_get_project_raises_on_404(httpx_mock):
    httpx_mock.add_response(
        method="GET",
        url="https://api.rylees.ai/v1/projects/missing",
        status_code=404,
    )
    client = ApiClient(api_token="api-tok")
    with pytest.raises(httpx.HTTPStatusError):
        client.get_project("missing")


def test_publish_release_note_sends_correct_payload(httpx_mock):
    httpx_mock.add_response(
        method="POST",
        url="https://api.rylees.ai/v1/projects/tok/release-history",
        status_code=201,
        json={"id": "rel-1", "status": "published", "version": "1.3.0"},
    )
    client = ApiClient(api_token="api-tok")
    payload = {
        "startRef": "v1.2.0",
        "endRef": "v1.3.0",
        "type": "tag",
        "branchName": "main",
        "body": "Diese Version enthält...",
        "versionBump": "minor",
    }
    response = client.publish_release_note("tok", payload)
    assert response["version"] == "1.3.0"

    request = httpx_mock.get_request()
    sent = json.loads(request.content)
    assert sent["startRef"] == "v1.2.0"
    assert sent["endRef"] == "v1.3.0"
    assert sent["type"] == "tag"
    assert sent["versionBump"] == "minor"
    assert request.headers["Authorization"] == "Bearer api-tok"


def test_publish_raises_on_error(httpx_mock):
    httpx_mock.add_response(
        method="POST",
        url="https://api.rylees.ai/v1/projects/tok/release-history",
        status_code=422,
    )
    client = ApiClient(api_token="api-tok")
    with pytest.raises(httpx.HTTPStatusError):
        client.publish_release_note("tok", {
            "startRef": "a",
            "endRef": "b",
            "type": "tag",
            "branchName": "",
            "body": "x",
            "versionBump": "minor",
        })
