using System;
using CodeRED.Plugin.Anime.Configuration;
using MediaBrowser.Common.Configuration;
using MediaBrowser.Common.Plugins;
using MediaBrowser.Model.Plugins;
using MediaBrowser.Model.Serialization;

namespace CodeRED.Plugin.Anime;

public sealed class Plugin : BasePlugin<PluginConfiguration>, IHasWebPages
{
    public const string PluginName = "CodeRED Anime";
    public static readonly Guid PluginGuid = Guid.Parse("7f3d30cc-784d-4b70-9843-55ed6f8f3b2d");

    public Plugin(IApplicationPaths applicationPaths, IXmlSerializer xmlSerializer)
        : base(applicationPaths, xmlSerializer)
    {
        Instance = this;
    }

    public static Plugin? Instance { get; private set; }

    public override string Name => PluginName;

    public override Guid Id => PluginGuid;

    public override string Description => "Expose CodeRED Anime catalog, metadata and playable streams inside Jellyfin without coupling Jellyfin to streaming providers.";

    public IEnumerable<PluginPageInfo> GetPages()
    {
        yield return new PluginPageInfo
        {
            Name = "codered-anime",
            EmbeddedResourcePath = GetType().Namespace + ".Configuration.configPage.html"
        };
    }
}
