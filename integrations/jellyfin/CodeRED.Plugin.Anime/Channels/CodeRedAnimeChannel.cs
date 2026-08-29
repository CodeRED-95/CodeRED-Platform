using CodeRED.Plugin.Anime.Api;
using CodeRED.Plugin.Anime.Models;
using MediaBrowser.Controller.Channels;
using MediaBrowser.Controller.Providers;
using MediaBrowser.Model.Channels;
using MediaBrowser.Model.Dto;
using MediaBrowser.Model.Entities;
using MediaBrowser.Model.MediaInfo;
using MediaBrowser.Model.Providers;

namespace CodeRED.Plugin.Anime.Channels;

public sealed class CodeRedAnimeChannel : IChannel, IRequiresMediaInfoCallback
{
    private readonly CodeRedAnimeClient _client;

    public CodeRedAnimeChannel(CodeRedAnimeClient client)
    {
        _client = client;
    }

    public string Name => Plugin.PluginName;

    public string Description => "Anime catalog and playback resolved through CodeRED Anime API.";

    public string DataVersion => "1";

    public string HomePageUrl => Plugin.Instance?.Configuration.CodeRedApiBaseUrl ?? string.Empty;

    public ChannelParentalRating ParentalRating => ChannelParentalRating.GeneralAudience;

    public bool IsEnabledFor(string userId)
    {
        return true;
    }

    public InternalChannelFeatures GetChannelFeatures()
    {
        return new InternalChannelFeatures
        {
            ContentTypes = new List<ChannelMediaContentType> { ChannelMediaContentType.Movie, ChannelMediaContentType.Episode },
            MediaTypes = new List<ChannelMediaType> { ChannelMediaType.Video },
            SupportsContentDownloading = false,
            SupportsSortOrderToggle = false
        };
    }

    public Task<DynamicImageResponse> GetChannelImage(ImageType type, CancellationToken cancellationToken)
    {
        return Task.FromResult(new DynamicImageResponse());
    }

    public IEnumerable<ImageType> GetSupportedChannelImages()
    {
        return Array.Empty<ImageType>();
    }

    public async Task<ChannelItemResult> GetChannelItems(InternalChannelItemQuery query, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(query.FolderId))
        {
            var search = Plugin.Instance?.Configuration.SearchBootstrapQuery ?? "one piece";
            var anime = await _client.SearchAsync(search, cancellationToken).ConfigureAwait(false);

            return new ChannelItemResult
            {
                Items = anime.Select(ToSeriesItem).ToArray(),
                TotalRecordCount = anime.Count
            };
        }

        if (TryParseSeries(query.FolderId, out var animeId))
        {
            var episodes = await _client.GetEpisodesAsync(animeId, cancellationToken).ConfigureAwait(false);

            return new ChannelItemResult
            {
                Items = episodes.Select(ToEpisodeItem).ToArray(),
                TotalRecordCount = episodes.Count
            };
        }

        return new ChannelItemResult
        {
            Items = Array.Empty<ChannelItemInfo>(),
            TotalRecordCount = 0
        };
    }

    public async Task<IEnumerable<MediaSourceInfo>> GetChannelItemMediaInfo(string id, CancellationToken cancellationToken)
    {
        if (Plugin.Instance?.Configuration.EnablePlayback == false || !TryParseEpisode(id, out var animeId, out var episode))
        {
            return Array.Empty<MediaSourceInfo>();
        }

        var preferredServer = Plugin.Instance?.Configuration.PreferredServer ?? "desu";
        var stream = await _client.GetStreamAsync(animeId, episode, preferredServer, cancellationToken).ConfigureAwait(false);
        if (stream is null)
        {
            return Array.Empty<MediaSourceInfo>();
        }

        return new[]
        {
            new MediaSourceInfo
            {
                Path = stream.Url,
                Protocol = MediaProtocol.Http,
                Container = stream.Format,
                IsRemote = true,
                RequiredHttpHeaders = stream.Headers is null
                    ? new Dictionary<string, string>()
                    : new Dictionary<string, string>(stream.Headers),
                SupportsDirectPlay = true,
                SupportsDirectStream = true
            }
        };
    }

    private static ChannelItemInfo ToSeriesItem(AnimeDto anime)
    {
        return new ChannelItemInfo
        {
            Id = "anime:" + anime.Id,
            Name = anime.Title,
            Type = ChannelItemType.Folder,
            ContentType = ChannelMediaContentType.Movie,
            ImageUrl = anime.Poster,
            Overview = anime.Description,
            PremiereDate = anime.Year.HasValue ? new DateTime(anime.Year.Value, 1, 1) : null,
            Genres = anime.Genres?.ToList() ?? new List<string>()
        };
    }

    private static ChannelItemInfo ToEpisodeItem(EpisodeDto episode)
    {
        return new ChannelItemInfo
        {
            Id = "episode:" + episode.AnimeId + ":" + episode.Number,
            Name = episode.Title ?? "Episodio " + episode.Number,
            Type = ChannelItemType.Media,
            ContentType = ChannelMediaContentType.Episode,
            MediaType = ChannelMediaType.Video,
            ImageUrl = episode.Thumbnail,
            IndexNumber = episode.Number,
            IsLiveStream = false
        };
    }

    private static bool TryParseSeries(string id, out string animeId)
    {
        const string prefix = "anime:";
        if (id.StartsWith(prefix, StringComparison.Ordinal))
        {
            animeId = id[prefix.Length..];
            return true;
        }

        animeId = string.Empty;
        return false;
    }

    private static bool TryParseEpisode(string id, out string animeId, out int episode)
    {
        const string prefix = "episode:";
        animeId = string.Empty;
        episode = 0;

        if (!id.StartsWith(prefix, StringComparison.Ordinal))
        {
            return false;
        }

        var payload = id[prefix.Length..];
        var separator = payload.LastIndexOf(':');
        if (separator <= 0 || separator >= payload.Length - 1)
        {
            return false;
        }

        animeId = payload[..separator];
        return int.TryParse(payload[(separator + 1)..], out episode);
    }
}
