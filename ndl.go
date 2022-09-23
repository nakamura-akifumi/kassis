package kassiscore

import (
	"encoding/xml"
	"fmt"
	"github.com/google/uuid"
	"github.com/nakamura-akifumi/kassis/internal/solr"
	"github.com/rs/zerolog/log"
	"net/url"
	"path"
	"strings"
)

type SearchRetrieveResponse struct {
	XMLName            xml.Name `xml:"searchRetrieveResponse"`
	Text               string   `xml:",chardata"`
	Xmlns              string   `xml:"xmlns,attr"`
	Version            string   `xml:"version"`
	NumberOfRecords    string   `xml:"numberOfRecords"`
	NextRecordPosition string   `xml:"nextRecordPosition"`
	ExtraResponseData  string   `xml:"extraResponseData"`
	Records            struct {
		Text   string `xml:",chardata"`
		Record []struct {
			Text          string `xml:",chardata"`
			RecordSchema  string `xml:"recordSchema"`
			RecordPacking string `xml:"recordPacking"`
			RecordData    struct {
				Text string `xml:",chardata"`
				RDF  NDLRDF `xml:"RDF"`
			} `xml:"recordData"`
			RecordPosition string `xml:"recordPosition"`
		} `xml:"record"`
	} `xml:"records"`
}

// DCNDL
type DCNDLOAIPMH struct {
	XMLName     xml.Name `xml:"OAI-PMH"`
	Text        string   `xml:",chardata"`
	Xmlns       string   `xml:"xmlns,attr"`
	ListRecords struct {
		Text   string `xml:",chardata"`
		Record []struct {
			Text   string `xml:",chardata"`
			Header struct {
				Text       string `xml:",chardata"`
				Identifier string `xml:"identifier"`
				Datestamp  string `xml:"datestamp"`
			} `xml:"header"`
			Metadata struct {
				Text string `xml:",chardata"`
				RDF  NDLRDF `xml:"RDF"`
			} `xml:"metadata"`
		} `xml:"record"`
		ResumptionToken struct {
			Text             string `xml:",chardata"`
			CompleteListSize string `xml:"completeListSize,attr"`
			Cursor           string `xml:"cursor,attr"`
		} `xml:"resumptionToken"`
	} `xml:"ListRecords"`
}

type NDLRDF struct {
	Text             string `xml:",chardata"`
	Dcterms          string `xml:"dcterms,attr"`
	Rdf              string `xml:"rdf,attr"`
	Dcndl            string `xml:"dcndl,attr"`
	Foaf             string `xml:"foaf,attr"`
	Rdfs             string `xml:"rdfs,attr"`
	Dc               string `xml:"dc,attr"`
	Owl              string `xml:"owl,attr"`
	BibAdminResource struct {
		Text                 string `xml:",chardata"`
		About                string `xml:"about,attr"`
		CatalogingStatus     string `xml:"catalogingStatus"`
		CatalogingRule       string `xml:"catalogingRule"`
		BibRecordCategory    string `xml:"bibRecordCategory"`
		BibRecordSubCategory string `xml:"bibRecordSubCategory"`
		Record               struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
		} `xml:"record"`
		Description string `xml:"description"`
	} `xml:"BibAdminResource"`
	BibResource struct {
		Text    string `xml:",chardata"`
		About   string `xml:"about,attr"`
		SeeAlso []struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
		} `xml:"seeAlso"`
		Identifier []struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"identifier"`
		Title struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"title"`
		Volume struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"volume"`
		SeriesTitle []struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"seriesTitle"`
		Creator []struct {
			Text  string `xml:",chardata"`
			Agent struct {
				Text          string `xml:",chardata"`
				About         string `xml:"about,attr"`
				Name          string `xml:"name"`
				Transcription string `xml:"transcription"`
			} `xml:"Agent"`
		} `xml:"creator"`
		Publisher []struct {
			Text  string `xml:",chardata"`
			Agent struct {
				Text          string `xml:",chardata"`
				Name          string `xml:"name"`
				Transcription string `xml:"transcription"`
				Location      string `xml:"location"`
				Description   string `xml:"description"`
			} `xml:"Agent"`
		} `xml:"publisher"`
		PublicationPlace struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"publicationPlace"`
		Date   string `xml:"date"`
		Issued struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"issued"`
		Subjects []struct {
			Text        string `xml:",chardata"`
			Datatype    string `xml:"datatype,attr"`
			Resource    string `xml:"resource,attr"`
			Description struct {
				Text          string `xml:",chardata"`
				About         string `xml:"about,attr"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"subject"`
		Language []struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"language"`
		Extent       []string `xml:"extent"`
		MaterialType []struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
			Label    string `xml:"label,attr"`
		} `xml:"materialType"`
		AccessRights string `xml:"accessRights"`
		Audience     string `xml:"audience"`
		Record       struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
		} `xml:"record"`
		PartInformation []struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text    string `xml:",chardata"`
				Title   string `xml:"title"`
				Creator string `xml:"creator"`
			} `xml:"Description"`
		} `xml:"partInformation"`
		Alternative []struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"alternative"`
		Descriptions []string `xml:"description"`
		Price        []string `xml:"price"`
		Edition      string   `xml:"edition"`
		Genre        []struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text  string `xml:",chardata"`
				About string `xml:"about,attr"`
				Value string `xml:"value"`
			} `xml:"Description"`
		} `xml:"genre"`
		SeriesCreator string `xml:"seriesCreator"`
		UniformTitle  struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				About         string `xml:"about,attr"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"uniformTitle"`
		PublicationPeriodicity string `xml:"publicationPeriodicity"`
		PublicationStatus      string `xml:"publicationStatus"`
		VolumeTitle            struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"volumeTitle"`
		VolumeRange string `xml:"volumeRange"`
		Relation    []struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
			Label    string `xml:"label,attr"`
		} `xml:"relation"`
		Replaces struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
			Label    string `xml:"label,attr"`
		} `xml:"replaces"`
		IsReplacedBy struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
			Label    string `xml:"label,attr"`
		} `xml:"isReplacedBy"`
		OriginalLanguage []struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"originalLanguage"`
	} `xml:"BibResource"`
	Item struct {
		Text         string `xml:",chardata"`
		About        string `xml:"about,attr"`
		HoldingAgent struct {
			Text  string `xml:",chardata"`
			Agent struct {
				Text       string `xml:",chardata"`
				Name       string `xml:"name"`
				Identifier struct {
					Text     string `xml:",chardata"`
					Datatype string `xml:"datatype,attr"`
				} `xml:"identifier"`
			} `xml:"Agent"`
		} `xml:"holdingAgent"`
		SeeAlso struct {
			Text     string `xml:",chardata"`
			Resource string `xml:"resource,attr"`
		} `xml:"seeAlso"`
		Identifier struct {
			Text     string `xml:",chardata"`
			Datatype string `xml:"datatype,attr"`
		} `xml:"identifier"`
		CallNumber      string   `xml:"callNumber"`
		LocalCallNumber []string `xml:"localCallNumber"`
		HoldingIssues   string   `xml:"holdingIssues"`
	} `xml:"Item"`
} /* `xml:"RDF"` */

func AddSolrDocumentNDLRDF(sc *solr.SingleClient, rdf *NDLRDF) error {
	materialid := rdf.BibAdminResource.About
	br := rdf.BibResource
	title := br.Title.Description.Value
	title_transcription := br.Title.Description.Transcription
	//uniform_title := br.UniformTitle.Description.Value
	//uniform_title_transcription := br.UniformTitle.Description.Transcription
	volume := br.Volume.Description.Value
	volume_transcription := br.Volume.Description.Transcription
	volume_title := br.VolumeTitle.Description.Value
	volume_title_transcription := br.VolumeTitle.Description.Transcription
	alternative := ""
	alternative_transcription := ""
	if len(br.Alternative) > 0 {
		alternative = br.Alternative[0].Description.Value
		alternative_transcription = br.Alternative[0].Description.Transcription
	}
	//TODO: データ確認
	//alternative_volume
	//alternative_volume_title
	series_title := ""
	series_title_transcription := ""
	if len(br.SeriesTitle) > 0 {
		//TODO: 配列対応
		series_title = br.SeriesTitle[0].Description.Value
		series_title_transcription = br.SeriesTitle[0].Description.Transcription
	}
	series_creator_literal := []string{}

	edition := br.Edition

	creators := []string{}
	creators_transcription := []string{}
	creators_agent_identifier := []string{}
	creators_literal := []string{}

	if len(br.Creator) > 0 {
		for _, c := range br.Creator {
			creators = append(creators, c.Agent.Name)
			creators_transcription = append(creators_transcription, c.Agent.Transcription)
			//TODO: creatorにすべきかagentに収めるか
			creators_agent_identifier = append(creators_agent_identifier, c.Agent.About)
			cts := strings.ReplaceAll(c.Text, "\n", "")
			cts = strings.TrimSpace(cts)
			creators_literal = append(creators_literal, cts)
		}
	}

	publisher := ""
	publisher_transcription := ""
	publisher_location := ""
	if len(br.Publisher) > 0 {
		publisher = br.Publisher[0].Agent.Name
		publisher_transcription = br.Publisher[0].Agent.Transcription
		publisher_location = br.Publisher[0].Agent.Location
	}

	//TODO: データ確認
	//creator_alternative_literal :=

	var language string
	if len(br.Language) > 0 {
		for _, x := range br.Language {
			if x.Datatype == "http://purl.org/dc/terms/ISO639-2" {
				language = x.Text
				break
			}
		}
		if language == "" {
			language = br.Language[0].Text
		}
	}
	var original_language string
	if len(br.OriginalLanguage) > 0 {
		for _, x := range br.OriginalLanguage {
			if x.Datatype == "http://purl.org/dc/terms/ISO639-2" {
				original_language = x.Text
				break
			}
		}
		if original_language == "" {
			original_language = br.OriginalLanguage[0].Text
		}
	}

	var subjects []string
	var subjects_transcription []string
	var subjects_resource []string
	if len(br.Subjects) > 0 {
		for _, x := range br.Subjects {
			subjects = append(subjects, x.Description.Value)
			subjects_transcription = append(subjects_transcription, x.Description.Transcription)
			if x.Description.About != "" {
				subjects_resource = append(subjects_resource, x.Description.About)
			} else {
				subjects_resource = append(subjects_resource, x.Resource)
			}
		}
	}

	var partInformation_title []string
	//var partInformation_transcription []string
	//var partInformation_description []string
	var partInformation_creator []string
	if len(br.PartInformation) > 0 {
		for _, x := range br.PartInformation {
			partInformation_title = append(partInformation_title, x.Description.Title)
			partInformation_creator = append(partInformation_creator, x.Description.Creator)
		}
	}

	var descriptions []string
	if len(br.Descriptions) > 0 {
		for _, x := range br.Descriptions {
			descriptions = append(descriptions, x)
		}
	}

	publication_date_literal := br.Date
	issued_w3cdtf := br.Issued.Text

	u, _ := url.Parse(br.PublicationPlace.Datatype)
	datatype := path.Base(u.Path)

	publication_place := datatype + "@" + br.PublicationPlace.Text

	u, _ = url.Parse(rdf.BibResource.MaterialType[0].Resource)
	mediatype := path.Base(u.Path)

	uuidObj, _ := uuid.NewUUID()
	id := uuidObj.String()

	// obj#2
	identifiers := []string{}
	for _, x := range br.Identifier {
		u, _ := url.Parse(x.Datatype)
		datatype := path.Base(u.Path)

		s := datatype + "@" + x.Text
		identifiers = append(identifiers, s)
	}

	m := SolrMaterial{
		ID:                       id,
		Materialid:               materialid,
		Objecttype:               "MENIFESTAION",
		Mediatype:                mediatype,
		Title:                    title,
		TitleTranscription:       title_transcription,
		Identifiers:              identifiers,
		Volume:                   volume,
		VolumeTranscription:      volume_transcription,
		VolumeTitle:              volume_title,
		VolumeTitleTranscription: volume_title_transcription,
		Alternative:              alternative,
		AlternativeTranscription: alternative_transcription,
		SeriesTitle:              series_title,
		SeriesTitleTranscription: series_title_transcription,
		Edition:                  edition,
		Creators:                 creators,
		CreatorsTranscription:    creators_transcription,
		CreatorsAgentIdentifier:  creators_agent_identifier,
		CreatorsLiteral:          creators_literal,
		Publisher:                publisher,
		PublisherTranscription:   publisher_transcription,
		PublisherAgentIdentifier: "",
		PublisherLocation:        publisher_location,
		PublicationPlace:         publication_place,
		SeriesCreatorLiteral:     series_creator_literal,
		Language:                 language,
		OriginalLanguage:         original_language,
		Subjects:                 subjects,
		SubjectsTranscription:    subjects_transcription,
		SubjectsResource:         subjects_resource,
		PartInformationTitle:     partInformation_title,
		PartInformationCreator:   partInformation_creator,
		Descriptions:             descriptions,
		PublicationDateLiteral:   publication_date_literal,
		PublicationDateFrom:      publication_date_literal,
		PublicationDateTo:        publication_date_literal,
		IssuedW3cdtf:             issued_w3cdtf,
	}

	err := AddSolrDocument(sc, m)
	if err != nil {
		fmt.Println("add error")
		fmt.Println(err)
		log.Fatal().Err(err)
		return err
	}
	return nil
}
