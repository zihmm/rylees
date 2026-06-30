import httpx
from app.models import ProjectConfig, PublishPayload, PublishResponse

BASE_URL = "https://api.rylees.ai/v1"

class ApiClient:
    def __init__(self, api_token: str, base_url: str = BASE_URL):
        self._client = httpx.Client(
            base_url=base_url,
            headers={
                "Authorization": f"Bearer {api_token}",
                "Accept": "application/json",
            },
            timeout=30.0,
        )

    def get_project(self, project_token: str) -> ProjectConfig:
        response = self._client.get(f"/projects/{project_token}")
        response.raise_for_status()
        data = response.json()
        return ProjectConfig(
            id=data["id"],
            name=data["name"],
            key=data["key"],
            description=data.get("description", ""),
            language=data.get("language", "en"),
            customer_name=data["customer"]["name"],
            customer_industry=data["customer"]["industry"],
            llm_temperature=data["llm"]["temperature"],
            llm_tonality=data["llm"]["tonality"],
        )

    def publish_release_note(
        self, project_token: str, payload: PublishPayload
    ) -> PublishResponse:
        response = self._client.post(
            f"/projects/{project_token}/release-history",
            json=payload,
        )
        response.raise_for_status()
        return response.json()

    def close(self):
        self._client.close()
