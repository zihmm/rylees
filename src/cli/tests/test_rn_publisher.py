from app.rn_publisher import RNPublisher


class FakeApiClient:
    def __init__(self):
        self.calls = []

    def publish_release_note(self, project_token, payload):
        self.calls.append((project_token, payload))
        return {"id": "rel-1", "status": "published", "version": "1.3.0"}


def test_publish_assembles_payload_correctly():
    fake = FakeApiClient()
    publisher = RNPublisher(fake, "proj-tok")
    publisher.publish(
        body="Die neue Version...",
        version_bump="minor",
        start_ref="v1.2.0",
        end_ref="v1.3.0",
        ref_type="tag",
    )
    token, payload = fake.calls[0]
    assert token == "proj-tok"
    assert payload["startRef"] == "v1.2.0"
    assert payload["endRef"] == "v1.3.0"
    assert payload["type"] == "tag"
    assert payload["versionBump"] == "minor"
    assert payload["body"] == "Die neue Version..."
    assert payload["branchName"] == ""


def test_publish_returns_response():
    fake = FakeApiClient()
    publisher = RNPublisher(fake, "proj-tok")
    response = publisher.publish(
        body="x",
        version_bump="patch",
        start_ref="abc",
        end_ref="def",
        ref_type="commits",
    )
    assert response["status"] == "published"
    assert response["version"] == "1.3.0"
