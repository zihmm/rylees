from typing import Literal
from app.api_client import ApiClient
from app.models import PublishPayload, PublishResponse

class RNPublisher:
    def __init__(self, api_client: ApiClient, project_token: str):
        self._client = api_client
        self._project_token = project_token

    def publish(
        self,
        body: str,
        version_bump: Literal["major", "minor", "patch"],
        start_ref: str,
        end_ref: str,
        ref_type: Literal["commits", "tag"],
        branch_name: str | None = None,
    ) -> PublishResponse:
        payload: PublishPayload = {
            "startRef": start_ref,
            "endRef": end_ref,
            "type": ref_type,
            "branchName": branch_name or "",
            "body": body,
            "versionBump": version_bump,
        }
        return self._client.publish_release_note(self._project_token, payload)
