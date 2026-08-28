using MediaBrowser.Model.Plugins;

namespace CodeRED.Plugin.Anime.Configuration;

public sealed class PluginConfiguration : BasePluginConfiguration
{
    public string CodeRedApiBaseUrl { get; set; } = "http://localhost/api/v1/anime";

    public string ApiToken { get; set; } = string.Empty;

    public string PreferredServer { get; set; } = "desu";

    public string SearchBootstrapQuery { get; set; } = "one piece";

    public int RequestTimeoutSeconds { get; set; } = 15;

    public bool EnablePlayback { get; set; } = true;
}
