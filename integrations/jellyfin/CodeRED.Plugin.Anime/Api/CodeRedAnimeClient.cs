using System.Net.Http.Headers;
using System.Net.Http.Json;
using CodeRED.Plugin.Anime.Configuration;
using CodeRED.Plugin.Anime.Models;

namespace CodeRED.Plugin.Anime.Api;

public sealed class CodeRedAnimeClient
{
    private readonly HttpClient _httpClient;

    public CodeRedAnimeClient(HttpClient httpClient)
    {
        _httpClient = httpClient;
    }

    public async Task<IReadOnlyList<AnimeDto>> SearchAsync(string query, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<IReadOnlyList<AnimeDto>>>(
            "search?q=" + Uri.EscapeDataString(query) + "&playable=1",
            cancellationToken).ConfigureAwait(false);

        return response.Data ?? Array.Empty<AnimeDto>();
    }

    public async Task<AnimeDto?> GetAnimeAsync(string animeId, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<AnimeDto>>(Uri.EscapeDataString(animeId), cancellationToken).ConfigureAwait(false);

        return response.Data;
    }

    public async Task<IReadOnlyList<SeasonDto>> GetSeasonsAsync(string animeId, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<IReadOnlyList<SeasonDto>>>(
            Uri.EscapeDataString(animeId) + "/seasons",
            cancellationToken).ConfigureAwait(false);

        return response.Data ?? Array.Empty<SeasonDto>();
    }

    public async Task<IReadOnlyList<EpisodeDto>> GetEpisodesAsync(string animeId, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<IReadOnlyList<EpisodeDto>>>(
            Uri.EscapeDataString(animeId) + "/episodes",
            cancellationToken).ConfigureAwait(false);

        return response.Data ?? Array.Empty<EpisodeDto>();
    }

    public async Task<IReadOnlyList<ServerDto>> GetServersAsync(string animeId, int episode, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<IReadOnlyList<ServerDto>>>(
            Uri.EscapeDataString(animeId) + "/episodes/" + episode + "/servers",
            cancellationToken).ConfigureAwait(false);

        return response.Data ?? Array.Empty<ServerDto>();
    }

    public async Task<StreamDto?> GetStreamAsync(string animeId, int episode, string server, CancellationToken cancellationToken)
    {
        var response = await GetAsync<Envelope<StreamDto>>(
            Uri.EscapeDataString(animeId) + "/episodes/" + episode + "/stream?server=" + Uri.EscapeDataString(server),
            cancellationToken).ConfigureAwait(false);

        return response.Data;
    }

    private async Task<T> GetAsync<T>(string relativePath, CancellationToken cancellationToken)
    {
        var config = Plugin.Instance?.Configuration ?? new PluginConfiguration();
        if (!Uri.TryCreate(config.CodeRedApiBaseUrl.TrimEnd('/') + "/", UriKind.Absolute, out var baseUri))
        {
            throw new InvalidOperationException("CodeRED Anime API base URL is not valid.");
        }

        using var request = new HttpRequestMessage(HttpMethod.Get, new Uri(baseUri, relativePath));
        request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

        if (!string.IsNullOrWhiteSpace(config.ApiToken))
        {
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", config.ApiToken);
        }

        using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeout.CancelAfter(TimeSpan.FromSeconds(Math.Clamp(config.RequestTimeoutSeconds, 1, 60)));

        using var response = await _httpClient.SendAsync(request, timeout.Token).ConfigureAwait(false);
        response.EnsureSuccessStatusCode();

        return await response.Content.ReadFromJsonAsync<T>(cancellationToken: timeout.Token).ConfigureAwait(false)
            ?? throw new InvalidOperationException("CodeRED Anime API returned an empty response.");
    }
}
