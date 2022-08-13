package kassiscore

import "encoding/xml"

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
		Subject []struct {
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
		Alternative struct {
			Text        string `xml:",chardata"`
			Description struct {
				Text          string `xml:",chardata"`
				Value         string `xml:"value"`
				Transcription string `xml:"transcription"`
			} `xml:"Description"`
		} `xml:"alternative"`
		Description []string `xml:"description"`
		Price       []string `xml:"price"`
		Edition     string   `xml:"edition"`
		Genre       []struct {
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
		OriginalLanguage struct {
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
