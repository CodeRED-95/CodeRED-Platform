using CodeRED.Plugin.Anime.Models;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace CodeRED.Plugin.Anime.Api;

[ApiController]
[Authorize]
[Route("CodeRedAnime")]
public sealed class CodeRedAnimeController : ControllerBase
{
    private readonly CodeRedAnimeClient _client;

    public CodeRedAnimeController(CodeRedAnimeClient client)
    {
        _client = client;
    }

    [HttpGet("Search")]
    public async Task<ActionResult<IReadOnlyList<AnimeDto>>> Search([FromQuery] string query, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(query) || query.Trim().Length < 2)
        {
            return BadRequest("Search query must contain at least two characters.");
        }

        return Ok(await _client.SearchAsync(query.Trim(), cancellationToken).ConfigureAwait(false));
    }

    [HttpGet("Episodes")]
    public async Task<ActionResult<IReadOnlyList<EpisodeDto>>> Episodes([FromQuery] string animeId, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(animeId))
        {
            return BadRequest("Anime id is required.");
        }

        return Ok(await _client.GetEpisodesAsync(animeId.Trim(), cancellationToken).ConfigureAwait(false));
    }

    [HttpGet("Stream")]
    public async Task<ActionResult<StreamDto>> Stream([FromQuery] string animeId, [FromQuery] int episode, [FromQuery] string? server, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(animeId) || episode < 1)
        {
            return BadRequest("Anime id and episode are required.");
        }

        var preferredServer = string.IsNullOrWhiteSpace(server)
            ? Plugin.Instance?.Configuration.PreferredServer ?? "desu"
            : server.Trim();
        var stream = await _client.GetStreamAsync(animeId.Trim(), episode, preferredServer, cancellationToken).ConfigureAwait(false);

        return stream is null ? NotFound() : Ok(stream);
    }
}
