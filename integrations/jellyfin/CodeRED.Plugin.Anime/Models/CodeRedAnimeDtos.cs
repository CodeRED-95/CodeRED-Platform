using System.Text.Json;
using System.Text.Json.Serialization;

namespace CodeRED.Plugin.Anime.Models;

public sealed record Envelope<T>(
    [property: JsonPropertyName("data")] T? Data,
    [property: JsonPropertyName("meta")] Dictionary<string, object>? Meta);

public sealed record AnimeDto(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("slug")] string Slug,
    [property: JsonPropertyName("title")] string Title,
    [property: JsonPropertyName("year")] int? Year,
    [property: JsonPropertyName("genres")] IReadOnlyList<string>? Genres,
    [property: JsonPropertyName("description")] string? Description,
    [property: JsonPropertyName("poster")] string? Poster,
    [property: JsonPropertyName("banner")] string? Banner,
    [property: JsonPropertyName("episodes")] int? Episodes);

public sealed record SeasonDto(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("anime_id")] string AnimeId,
    [property: JsonPropertyName("number")] int Number,
    [property: JsonPropertyName("title")] string? Title,
    [property: JsonPropertyName("episodes")] IReadOnlyList<EpisodeDto>? Episodes);

public sealed record EpisodeDto(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("anime_id")] string AnimeId,
    [property: JsonPropertyName("number")] int Number,
    [property: JsonPropertyName("title")] string? Title,
    [property: JsonPropertyName("language")] string? Language,
    [property: JsonPropertyName("thumbnail")] string? Thumbnail);

public sealed record ServerDto(
    [property: JsonPropertyName("id")] string Id,
    [property: JsonPropertyName("name")] string Name,
    [property: JsonPropertyName("type")] string Type,
    [property: JsonPropertyName("language")] string? Language);

public sealed record StreamDto(
    [property: JsonPropertyName("url")] string Url,
    [property: JsonPropertyName("type")] string Type,
    [property: JsonPropertyName("format")] string Format,
    [property: JsonPropertyName("headers")] JsonElement? Headers,
    [property: JsonPropertyName("expires_at")] string? ExpiresAt)
{
    public Dictionary<string, string> GetHeaders()
    {
        if (Headers is not { ValueKind: JsonValueKind.Object } headers)
        {
            return new Dictionary<string, string>();
        }

        var normalized = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        foreach (var header in headers.EnumerateObject())
        {
            if (header.Value.ValueKind == JsonValueKind.String)
            {
                normalized[header.Name] = header.Value.GetString() ?? string.Empty;
            }
        }

        return normalized;
    }
}
